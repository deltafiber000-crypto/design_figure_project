<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Services\SnapshotPdfService;
use App\Services\SvgRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class QuoteController extends Controller
{
    private const ACCOUNT_NAME_SOURCE_INTERNAL = 'internal_name';
    private const ACCOUNT_NAME_SOURCE_USER = 'user_name';

    public function index()
    {
        $customerEmails = DB::table('account_user as au')
            ->join('users as u', 'u.id', '=', 'au.user_id')
            ->whereColumn('au.account_id', 'q.account_id')
            ->where('au.role', 'customer')
            ->selectRaw("string_agg(u.email, ', ')");

        $quotes = DB::table('quotes as q')
            ->join('accounts as a', 'a.id', '=', 'q.account_id')
            ->select('q.*')
            ->selectRaw("
                coalesce(
                    nullif(a.internal_name, ''),
                    (
                        select u2.name
                        from account_user as au2
                        join users as u2 on u2.id = au2.user_id
                        where au2.account_id = a.id
                        order by
                            case au2.role
                                when 'customer' then 1
                                when 'admin' then 2
                                when 'sales' then 3
                                else 9
                            end,
                            au2.user_id
                        limit 1
                    ),
                    '-'
                ) as account_display_name
            ")
            ->addSelect('a.internal_name as account_name', 'a.internal_name as account_internal_name', 'a.assignee_name')
            ->selectSub($customerEmails, 'customer_emails')
            ->orderBy('q.id', 'desc')
            ->limit(200)
            ->get();

        return view('ops.quotes.index', ['quotes' => $quotes]);
    }

    public function show(int $id, SvgRenderer $renderer)
    {
        $customerEmails = DB::table('account_user as au')
            ->join('users as u', 'u.id', '=', 'au.user_id')
            ->whereColumn('au.account_id', 'q.account_id')
            ->where('au.role', 'customer')
            ->selectRaw("string_agg(u.email, ', ')");

        $accountUserName = DB::table('account_user as au2')
            ->join('users as u2', 'u2.id', '=', 'au2.user_id')
            ->whereColumn('au2.account_id', 'a.id')
            ->orderByRaw("
                case au2.role
                    when 'customer' then 1
                    when 'admin' then 2
                    when 'sales' then 3
                    else 9
                end
            ")
            ->orderBy('au2.user_id')
            ->select('u2.name')
            ->limit(1);

        $quote = DB::table('quotes as q')
            ->join('accounts as a', 'a.id', '=', 'q.account_id')
            ->leftJoin('configurator_sessions as cs', 'cs.id', '=', 'q.session_id')
            ->select('q.*')
            ->addSelect('a.internal_name as account_internal_name', 'a.assignee_name')
            ->selectSub($accountUserName, 'account_user_name')
            ->addSelect('cs.memo as session_memo')
            ->selectSub($customerEmails, 'customer_emails')
            ->where('q.id', $id)
            ->first();
        if (!$quote) abort(404);

        $quoteMemo = trim((string)($quote->memo ?? ''));
        $sessionMemo = trim((string)($quote->session_memo ?? ''));
        $quote->display_memo = $quoteMemo !== '' ? $quoteMemo : $sessionMemo;

        $snapshot = $this->decodeJson($quote->snapshot) ?? [];
        $nameSource = $this->resolveAccountNameSource($snapshot);
        $quote->account_display_name_source = $nameSource;
        $quote->account_display_name = $this->resolveAccountDisplayName(
            $nameSource,
            (string)($quote->account_internal_name ?? ''),
            (string)($quote->account_user_name ?? '')
        );
        $quote->account_name = $quote->account_display_name;

        $config = $snapshot['config'] ?? [];
        $derived = $snapshot['derived'] ?? [];
        $errors = $snapshot['validation_errors'] ?? [];

        $svg = $renderer->render($config, $derived, $errors);
        $totals = $snapshot['totals'] ?? [];

        $requests = DB::table('change_requests')
            ->where('change_requests.entity_type', 'quote')
            ->where('change_requests.entity_id', $id)
            ->leftJoin('users as requester', 'requester.id', '=', 'change_requests.requested_by')
            ->leftJoin('users as approver', 'approver.id', '=', 'change_requests.approved_by')
            ->select('change_requests.*')
            ->addSelect('requester.email as requested_by_email', 'approver.email as approved_by_email')
            ->selectSub(
                DB::table('account_user as au')
                    ->join('accounts as a', 'a.id', '=', 'au.account_id')
                    ->join('users as u', 'u.id', '=', 'au.user_id')
                    ->whereColumn('au.user_id', 'change_requests.requested_by')
                    ->selectRaw("coalesce(nullif(a.internal_name, ''), u.name)")
                    ->orderBy('au.account_id')
                    ->limit(1),
                'requested_by_account_display_name'
            )
            ->selectSub(
                DB::table('account_user as au')
                    ->join('accounts as a', 'a.id', '=', 'au.account_id')
                    ->whereColumn('au.user_id', 'change_requests.requested_by')
                    ->select('a.assignee_name')
                    ->orderBy('au.account_id')
                    ->limit(1),
                'requested_by_assignee_name'
            )
            ->selectSub(
                DB::table('account_user as au')
                    ->join('accounts as a', 'a.id', '=', 'au.account_id')
                    ->join('users as u', 'u.id', '=', 'au.user_id')
                    ->whereColumn('au.user_id', 'change_requests.approved_by')
                    ->selectRaw("coalesce(nullif(a.internal_name, ''), u.name)")
                    ->orderBy('au.account_id')
                    ->limit(1),
                'approved_by_account_display_name'
            )
            ->orderBy('change_requests.id', 'desc')
            ->get();

        return view('ops.quotes.show', [
            'quote' => $quote,
            'snapshotJson' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'snapshot' => $snapshot,
            'configJson' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'derivedJson' => json_encode($derived, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'errorsJson' => json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'totals' => [
                'subtotal' => $totals['subtotal'] ?? null,
                'tax' => $totals['tax'] ?? null,
                'total' => $totals['total'] ?? null,
            ],
            'svg' => $svg,
            'requests' => $requests,
        ]);
    }

    public function edit(int $id)
    {
        $quote = DB::table('quotes as q')
            ->leftJoin('configurator_sessions as cs', 'cs.id', '=', 'q.session_id')
            ->where('q.id', $id)
            ->select('q.*')
            ->addSelect('cs.memo as session_memo')
            ->first();
        if (!$quote) abort(404);

        $snapshot = $this->decodeJson($quote->snapshot) ?? [];
        $config = is_array($snapshot['config'] ?? null) ? $snapshot['config'] : [];
        $templateVersionId = (int)($snapshot['template_version_id'] ?? 0);
        $displayNameSource = $this->resolveAccountNameSource($snapshot);
        $quoteMemo = trim((string)($quote->memo ?? ''));
        $sessionMemo = trim((string)($quote->session_memo ?? ''));
        $initialMemo = $quoteMemo !== '' ? $quoteMemo : $sessionMemo;

        return view('ops.quotes.edit', [
            'quote' => $quote,
            'initialConfig' => $config,
            'templateVersionId' => $templateVersionId,
            'initialMemo' => $initialMemo,
            'displayNameSource' => $displayNameSource,
        ]);
    }

    public function updateDisplayNameSource(Request $request, int $id)
    {
        $quote = DB::table('quotes')->where('id', $id)->first();
        if (!$quote) abort(404);

        $data = $request->validate([
            'display_name_source' => 'required|in:'.self::ACCOUNT_NAME_SOURCE_INTERNAL.','.self::ACCOUNT_NAME_SOURCE_USER,
        ]);

        $snapshot = $this->decodeJson($quote->snapshot) ?? [];
        $snapshot['account_display_name_source'] = $data['display_name_source'];

        DB::table('quotes')->where('id', $id)->update([
            'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('ops.quotes.edit', $id)
            ->with('status', '概要カードの表示名設定を更新しました');
    }

    public function editRequest(int $id)
    {
        $quote = DB::table('quotes')->where('id', $id)->first();
        if (!$quote) abort(404);

        $snapshot = $this->decodeJson($quote->snapshot) ?? [];
        $totals = $snapshot['totals'] ?? [];

        return view('ops.quotes.edit-request', [
            'quote' => $quote,
            'snapshotJson' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'totals' => [
                'subtotal' => $totals['subtotal'] ?? null,
                'tax' => $totals['tax'] ?? null,
                'total' => $totals['total'] ?? null,
            ],
        ]);
    }

    public function storeEditRequest(Request $request, int $id)
    {
        $quote = DB::table('quotes')->where('id', $id)->first();
        if (!$quote) abort(404);

        $data = $request->validate([
            'snapshot_json' => 'nullable|string',
            'comment' => 'nullable|string',
            'subtotal' => 'nullable|numeric',
            'tax' => 'nullable|numeric',
            'total' => 'nullable|numeric',
        ]);

        $baseSnapshot = $this->decodeJson($quote->snapshot) ?? [];
        $nameSource = $this->resolveAccountNameSource($baseSnapshot);
        if (!array_key_exists('account_display_name_source', $baseSnapshot)) {
            $baseSnapshot['account_display_name_source'] = $nameSource;
        }
        if (!array_key_exists('memo', $baseSnapshot)) {
            $baseSnapshot['memo'] = $quote->memo;
        }

        $decoded = null;
        if (!empty($data['snapshot_json'])) {
            $decoded = json_decode($data['snapshot_json'], true);
            if (!is_array($decoded)) {
                return back()->withErrors(['snapshot_json' => 'snapshotはJSON形式で入力してください'])->withInput();
            }
        } else {
            $decoded = $this->decodeJson($quote->snapshot) ?? [];
            $decoded['totals'] = is_array($decoded['totals'] ?? null) ? $decoded['totals'] : [];
            if (isset($data['subtotal'])) $decoded['totals']['subtotal'] = (float)$data['subtotal'];
            if (isset($data['tax'])) $decoded['totals']['tax'] = (float)$data['tax'];
            if (isset($data['total'])) $decoded['totals']['total'] = (float)$data['total'];
        }
        if (!array_key_exists('account_display_name_source', $decoded)) {
            $decoded['account_display_name_source'] = $nameSource;
        }
        if (!array_key_exists('memo', $decoded)) {
            $decoded['memo'] = $quote->memo;
        }

        DB::table('change_requests')->insert([
            'entity_type' => 'quote',
            'entity_id' => $id,
            'proposed_json' => json_encode([
                'snapshot' => $decoded,
                'base_snapshot' => $baseSnapshot,
            ], JSON_UNESCAPED_UNICODE),
            'status' => 'PENDING',
            'requested_by' => (int)$request->user()->id,
            'comment' => $data['comment'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('ops.quotes.show', $id)->with('status', '承認リクエストを送信しました');
    }

    public function updateMemo(Request $request, int $id)
    {
        $quote = DB::table('quotes')->where('id', $id)->first();
        if (!$quote) abort(404);

        $data = $request->validate([
            'memo' => 'nullable|string|max:5000',
        ]);
        $memo = trim((string)($data['memo'] ?? ''));
        if ($memo === '') $memo = null;

        DB::table('quotes')->where('id', $id)->update([
            'memo' => $memo,
            'updated_at' => now(),
        ]);

        return redirect()->route('ops.quotes.show', $id)->with('status', '見積メモを更新しました');
    }

    private function decodeJson(mixed $value): ?array
    {
        if (is_array($value)) return $value;
        if ($value === null) return null;
        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function downloadSnapshotPdf(int $id, SvgRenderer $renderer, SnapshotPdfService $pdfService)
    {
        $customerEmails = DB::table('account_user as au')
            ->join('users as u', 'u.id', '=', 'au.user_id')
            ->whereColumn('au.account_id', 'q.account_id')
            ->where('au.role', 'customer')
            ->selectRaw("string_agg(u.email, ', ')");

        $accountUserName = DB::table('account_user as au2')
            ->join('users as u2', 'u2.id', '=', 'au2.user_id')
            ->whereColumn('au2.account_id', 'a.id')
            ->orderByRaw("
                case au2.role
                    when 'customer' then 1
                    when 'admin' then 2
                    when 'sales' then 3
                    else 9
                end
            ")
            ->orderBy('au2.user_id')
            ->select('u2.name')
            ->limit(1);

        $quote = DB::table('quotes as q')
            ->join('accounts as a', 'a.id', '=', 'q.account_id')
            ->leftJoin('configurator_sessions as cs', 'cs.id', '=', 'q.session_id')
            ->select('q.*')
            ->addSelect('a.internal_name as account_internal_name', 'a.assignee_name')
            ->selectSub($accountUserName, 'account_user_name')
            ->addSelect('cs.memo as session_memo')
            ->selectSub($customerEmails, 'customer_emails')
            ->where('q.id', $id)
            ->first();
        if (!$quote) abort(404);

        $quoteMemo = trim((string)($quote->memo ?? ''));
        $sessionMemo = trim((string)($quote->session_memo ?? ''));
        $quote->display_memo = $quoteMemo !== '' ? $quoteMemo : $sessionMemo;

        $snapshot = $this->decodeJson($quote->snapshot) ?? [];
        $nameSource = $this->resolveAccountNameSource($snapshot);
        $quote->account_display_name_source = $nameSource;
        $quote->account_display_name = $this->resolveAccountDisplayName(
            $nameSource,
            (string)($quote->account_internal_name ?? ''),
            (string)($quote->account_user_name ?? '')
        );
        $quote->account_name = $quote->account_display_name;

        $config = $snapshot['config'] ?? [];
        $derived = $snapshot['derived'] ?? [];
        $errors = $snapshot['validation_errors'] ?? [];
        $requestCount = (int)DB::table('change_requests')
            ->where('entity_type', 'quote')
            ->where('entity_id', $id)
            ->count();

        $svg = $renderer->render($config, $derived, $errors);

        $filename = $pdfService->buildFilename(
            'quote',
            (int)$quote->account_id,
            (int)($snapshot['template_version_id'] ?? 0),
            $snapshot,
            $config,
            $derived,
            (string)$quote->updated_at
        );

        return $pdfService->downloadSnapshotBundleUi([
            'title' => '見積 スナップショット',
            'panelTitle' => '見積スナップショット',
            'summaryItems' => [
                ['label' => '見積ID', 'value' => $quote->id],
                ['label' => 'ステータス', 'value' => $quote->status],
                ['label' => 'アカウント表示名', 'value' => $quote->account_display_name ?? ''],
                ['label' => '担当者', 'value' => $quote->assignee_name ?? '-'],
                ['label' => '登録メールアドレス', 'value' => $quote->customer_emails ?? '-'],
                ['label' => '承認リクエスト件数', 'value' => $requestCount],
            ],
            'showMemoCard' => true,
            'memoValue' => $quote->display_memo ?? '',
            'memoLabel' => 'メモ',
            'showCreatorColumns' => false,
            'summaryTableColumns' => 4,
            'svg' => $svg,
            'snapshot' => $snapshot,
            'config' => is_array($config) ? $config : [],
            'derived' => is_array($derived) ? $derived : [],
            'errors' => is_array($errors) ? $errors : [],
        ], $filename);
    }

    private function resolveAccountNameSource(array $snapshot): string
    {
        $source = (string)($snapshot['account_display_name_source'] ?? self::ACCOUNT_NAME_SOURCE_INTERNAL);
        if (!in_array($source, [self::ACCOUNT_NAME_SOURCE_INTERNAL, self::ACCOUNT_NAME_SOURCE_USER], true)) {
            return self::ACCOUNT_NAME_SOURCE_INTERNAL;
        }
        return $source;
    }

    private function resolveAccountDisplayName(string $source, string $internalName, string $userName): string
    {
        $internalName = trim($internalName);
        $userName = trim($userName);

        if ($source === self::ACCOUNT_NAME_SOURCE_USER) {
            if ($userName !== '') {
                return $userName;
            }
            if ($internalName !== '') {
                return $internalName;
            }
            return '-';
        }

        if ($internalName !== '') {
            return $internalName;
        }
        if ($userName !== '') {
            return $userName;
        }
        return '-';
    }
}
