<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Services\SnapshotPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ConfiguratorSessionController extends Controller
{
    public function index()
    {
        $customerNames = DB::table('account_user as au')
            ->join('users as u', 'u.id', '=', 'au.user_id')
            ->whereColumn('au.account_id', 'configurator_sessions.account_id')
            ->where('au.role', 'customer')
            ->selectRaw("string_agg(u.name, ', ')");

        $sessions = DB::table('configurator_sessions')
            ->select('configurator_sessions.*')
            ->selectSub($customerNames, 'customer_names')
            ->orderBy('id', 'desc')
            ->limit(200)
            ->get();

        return view('ops.sessions.index', ['sessions' => $sessions]);
    }

    public function show(int $id, \App\Services\SvgRenderer $renderer)
    {
        $customerNames = DB::table('account_user as au')
            ->join('users as u', 'u.id', '=', 'au.user_id')
            ->whereColumn('au.account_id', 'configurator_sessions.account_id')
            ->where('au.role', 'customer')
            ->selectRaw("string_agg(u.name, ', ')");

        $session = DB::table('configurator_sessions')
            ->select('configurator_sessions.*')
            ->selectSub($customerNames, 'customer_names')
            ->where('id', $id)
            ->first();
        if (!$session) abort(404);

        $config = $this->decodeJson($session->config) ?? [];
        $derived = $this->decodeJson($session->derived) ?? [];
        $errors = $this->decodeJson($session->validation_errors) ?? [];

        $requests = DB::table('change_requests')
            ->where('entity_type', 'configurator_session')
            ->where('entity_id', $id)
            ->orderBy('id', 'desc')
            ->get();

        $svg = $renderer->render($config, $derived, $errors);

        return view('ops.sessions.show', [
            'session' => $session,
            'configJson' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'derivedJson' => json_encode($derived, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'errorsJson' => json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'svg' => $svg,
            'requests' => $requests,
        ]);
    }

    public function downloadSnapshotPdf(int $id, \App\Services\SvgRenderer $renderer, SnapshotPdfService $pdfService)
    {
        $session = DB::table('configurator_sessions')->where('id', $id)->first();
        if (!$session) abort(404);

        $config = $this->decodeJson($session->config) ?? [];
        $derived = $this->decodeJson($session->derived) ?? [];
        $errors = $this->decodeJson($session->validation_errors) ?? [];

        $svg = $renderer->render($config, $derived, $errors);

        return $pdfService->download(
            '構成セッション スナップショット',
            $svg,
            "configurator_session_{$id}_snapshot.pdf"
        );
    }

    public function editRequest(int $id)
    {
        $session = DB::table('configurator_sessions')->where('id', $id)->first();
        if (!$session) abort(404);

        $config = $this->decodeJson($session->config) ?? [];
        $connectors = is_array($config['connectors'] ?? null) ? $config['connectors'] : [];

        return view('ops.sessions.edit-request', [
            'session' => $session,
            'configJson' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'simple' => [
                'mfdCount' => $config['mfdCount'] ?? null,
                'tubeCount' => $config['tubeCount'] ?? null,
                'connectors_mode' => $connectors['mode'] ?? null,
                'connectors_left' => $connectors['leftSkuCode'] ?? null,
                'connectors_right' => $connectors['rightSkuCode'] ?? null,
            ],
        ]);
    }

    public function storeEditRequest(Request $request, int $id)
    {
        $session = DB::table('configurator_sessions')->where('id', $id)->first();
        if (!$session) abort(404);

        $data = $request->validate([
            'config_json' => 'nullable|string',
            'comment' => 'nullable|string',
            'mfd_count' => 'nullable|integer|min:1|max:10',
            'tube_count' => 'nullable|integer|min:0',
            'connectors_mode' => 'nullable|string',
            'connectors_left' => 'nullable|string',
            'connectors_right' => 'nullable|string',
        ]);

        $decoded = null;
        if (!empty($data['config_json'])) {
            $decoded = json_decode($data['config_json'], true);
            if (!is_array($decoded)) {
                return back()->withErrors(['config_json' => 'configはJSON形式で入力してください'])->withInput();
            }
        } else {
            $decoded = $this->decodeJson($session->config) ?? [];
            if (isset($data['mfd_count'])) {
                $decoded['mfdCount'] = (int)$data['mfd_count'];
            }
            if (isset($data['tube_count'])) {
                $decoded['tubeCount'] = (int)$data['tube_count'];
            }
            if (!empty($data['connectors_mode']) || isset($data['connectors_left']) || isset($data['connectors_right'])) {
                $decoded['connectors'] = is_array($decoded['connectors'] ?? null) ? $decoded['connectors'] : [];
                if (!empty($data['connectors_mode'])) {
                    $decoded['connectors']['mode'] = $data['connectors_mode'];
                }
                if (array_key_exists('connectors_left', $data)) {
                    $decoded['connectors']['leftSkuCode'] = $data['connectors_left'] !== '' ? $data['connectors_left'] : null;
                }
                if (array_key_exists('connectors_right', $data)) {
                    $decoded['connectors']['rightSkuCode'] = $data['connectors_right'] !== '' ? $data['connectors_right'] : null;
                }
            }
        }

        DB::table('change_requests')->insert([
            'entity_type' => 'configurator_session',
            'entity_id' => $id,
            'proposed_json' => json_encode(['config' => $decoded], JSON_UNESCAPED_UNICODE),
            'status' => 'PENDING',
            'requested_by' => (int)$request->user()->id,
            'comment' => $data['comment'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('ops.sessions.show', $id)->with('status', '承認リクエストを送信しました');
    }

    private function decodeJson(mixed $value): ?array
    {
        if (is_array($value)) return $value;
        if ($value === null) return null;
        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : null;
    }
}
