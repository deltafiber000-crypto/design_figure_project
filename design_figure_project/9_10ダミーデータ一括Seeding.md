今は最小限のダミーデータSeederを入れてますが、逆に各テーブル10データぐらいのダミーデータを挿入できるSeederも佐生精してください。スキーマ（DB構造）は以下に示します。

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('account_type'); // 'B2B' or 'B2C'
            $table->string('name');
            $table->timestampsTz();
        });

        Schema::create('account_user', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('user_id'); // Laravel標準usersテーブルを使う想定
            $table->string('role'); // admin/sales/customer
            $table->timestampsTz();

            $table->primary(['account_id', 'user_id']);
            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('user_id')->references('id')->on('users');
        });

        Schema::create('skus', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('sku_code')->unique(); // 例: FIBER_A
            $table->string('name');
            $table->string('category'); // PROC/SLEEVE/FIBER/TUBE/CONNECTOR
            $table->boolean('active')->default(true);
            $table->jsonb('attributes')->default('{}'); // SKU属性（研磨仕様などもここに）
            $table->timestampsTz();

            $table->index('category');
        });

        Schema::create('price_books', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->integer('version');
            $table->string('currency')->default('JPY');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestampsTz();

            $table->unique(['name', 'version']);
        });

        Schema::create('price_book_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('price_book_id');
            $table->unsignedBigInteger('sku_id');

            $table->string('pricing_model'); // FIXED/PER_MM/FORMULA
            $table->decimal('unit_price', 12, 2)->nullable();     // FIXED
            $table->decimal('price_per_mm', 12, 6)->nullable();   // PER_MM
            $table->jsonb('formula')->nullable();                 // FORMULA（JSON式）

            $table->decimal('min_qty', 12, 3)->default(1);
            $table->timestampsTz();

            $table->foreign('price_book_id')->references('id')->on('price_books');
            $table->foreign('sku_id')->references('id')->on('skus');

            $table->index(['price_book_id']);
            $table->index(['sku_id']);
        });

        Schema::create('product_templates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('template_code')->unique(); // MFD_CONVERSION_FIBER
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('product_template_versions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('template_id');
            $table->integer('version');
            $table->string('dsl_version'); // 0.2など
            $table->jsonb('dsl_json');
            $table->timestampsTz();

            $table->foreign('template_id')->references('id')->on('product_templates');
            $table->unique(['template_id', 'version']);
        });

        Schema::create('configurator_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('template_version_id');

            $table->string('status'); // DRAFT/LOCKED/QUOTED/EXPIRED
            $table->jsonb('config');  // ユーザー入力の正本（config）
            $table->jsonb('derived')->default('{}'); // fiberCount等
            $table->jsonb('validation_errors')->default('[]'); // エラー配列

            $table->timestampsTz();

            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('template_version_id')->references('id')->on('product_template_versions');

            $table->index('account_id');
            $table->index('status');
        });

        Schema::create('quotes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('session_id');

            $table->string('status'); // ISSUED/ORDERED/CANCELLED
            $table->string('currency')->default('JPY');

            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2);

            $table->jsonb('snapshot'); // テンプレ版、価格表、BOM、計算内訳など
            $table->timestampsTz();

            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('session_id')->references('id')->on('configurator_sessions');

            $table->index('account_id');
        });

        Schema::create('quote_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('quote_id');
            $table->unsignedBigInteger('sku_id');

            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 12, 6);
            $table->decimal('line_total', 12, 2);

            $table->jsonb('options')->default('{}'); // lengthMm, toleranceMm 等
            $table->string('source_path')->nullable(); // $.fibers[1] など
            $table->integer('sort_order')->default(0);

            $table->timestampsTz();

            $table->foreign('quote_id')->references('id')->on('quotes');
            $table->foreign('sku_id')->references('id')->on('skus');

            $table->index('quote_id');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('action');      // CREATE_SKU 等
            $table->string('entity_type'); // sku/quote/template 等
            $table->unsignedBigInteger('entity_id')->nullable();

            $table->jsonb('before_json')->nullable();
            $table->jsonb('after_json')->nullable();

            $table->timestampsTz();

            $table->foreign('actor_user_id')->references('id')->on('users');
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('configurator_sessions');
        Schema::dropIfExists('product_template_versions');
        Schema::dropIfExists('product_templates');
        Schema::dropIfExists('price_book_items');
        Schema::dropIfExists('price_books');
        Schema::dropIfExists('skus');
        Schema::dropIfExists('account_user');
        Schema::dropIfExists('accounts');
    }
};












了解です。提示いただいたスキーマ（schema：DB構造）に合わせて、**各テーブルにだいたい10件ずつ**入る「ダミーデータSeeder（シーダー：初期データ投入）」を用意します。
（※`users` テーブル（Laravel標準：利用者）も必要になるので **10ユーザー**作ります）

以下は **そのままコピペで動く**想定です。

---

# 1) 作るSeeder（シーダー：初期データ投入）

## 1-1) コマンド（command：命令）でSeederファイル作成

`docker-compose.yml` があるフォルダ（たぶん `mfd-mvp`）で：

```bash
docker compose exec app php artisan make:seeder DemoDataSeeder
```

---

## 1-2) 生成されたSeederにコードを貼る

作られるファイル：`database/seeders/DemoDataSeeder.php`
中身を **全部** これに置き換えてください。

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// Model（モデル：DB操作クラス）
use App\Models\User;
use App\Models\ConfiguratorSession;
use App\Models\ConfiguratorSession as SessionModel;

final class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // 乱数（random：ランダム）を毎回同じにしたいなら seed（シード：種）を固定
        // mt_srand(1234);

        DB::transaction(function () {
            // =========
            // 0) users（Laravel標準）
            // =========
            $this->seedUsers(10);

            // =========
            // 1) accounts / account_user
            // =========
            $accountIds = $this->seedAccounts(10);
            $userIds = User::query()->orderBy('id')->limit(10)->pluck('id')->all();
            $this->seedAccountUser($accountIds, $userIds);

            // =========
            // 2) skus
            // =========
            $skuRows = $this->seedSkus();         // だいたい10〜15件
            $skuIdByCode = DB::table('skus')->pluck('id', 'sku_code')->all();

            // =========
            // 3) price_books / price_book_items
            // =========
            $priceBookIds = $this->seedPriceBooks(10);
            $this->seedPriceBookItems($priceBookIds, $skuIdByCode, 10);

            // =========
            // 4) product_templates / product_template_versions
            // =========
            $templateVersionIds = $this->seedTemplatesAndVersions(10);

            // =========
            // 5) configurator_sessions（config/derived/validation_errors）
            // =========
            $sessionIds = $this->seedConfiguratorSessions(10, $accountIds, $templateVersionIds);

            // =========
            // 6) quotes / quote_items
            // =========
            $quoteIds = $this->seedQuotesAndItems(10, $accountIds, $sessionIds, $skuIdByCode);

            // =========
            // 7) audit_logs
            // =========
            $this->seedAuditLogs(10, $userIds, $quoteIds, $templateVersionIds, $skuIdByCode);
        });
    }

    // -------------------------
    // users
    // -------------------------
    private function seedUsers(int $n): void
    {
        // 既に10人以上いれば追加しない（updateOrInsert：なければ作る）
        $existing = User::query()->count();
        if ($existing >= $n) return;

        $toCreate = $n - $existing;

        for ($i = 1; $i <= $toCreate; $i++) {
            $num = $existing + $i;
            User::query()->create([
                'name' => "Demo User {$num}",
                'email' => "demo{$num}@example.com",
                'password' => Hash::make('password'), // password（パスワード：合言葉）
            ]);
        }
    }

    // -------------------------
    // accounts
    // -------------------------
    private function seedAccounts(int $n): array
    {
        // 既存を尊重しつつ不足分だけ追加
        $existingIds = DB::table('accounts')->orderBy('id')->pluck('id')->all();
        $need = $n - count($existingIds);

        if ($need > 0) {
            $rows = [];
            for ($i = 1; $i <= $need; $i++) {
                $idx = count($existingIds) + $i;
                $rows[] = [
                    'account_type' => ($idx % 2 === 0) ? 'B2B' : 'B2C',
                    'name' => "Demo Account {$idx}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('accounts')->insert($rows);
        }

        return DB::table('accounts')->orderBy('id')->limit($n)->pluck('id')->all();
    }

    // -------------------------
    // account_user（pivot：中間テーブル）
    // -------------------------
    private function seedAccountUser(array $accountIds, array $userIds): void
    {
        $roles = ['admin', 'sales', 'customer'];

        $rows = [];
        $pairs = 0;

        // 各account（アカウント：顧客）にユーザー2人ぐらい紐づける
        foreach ($accountIds as $ai => $accountId) {
            $u1 = $userIds[$ai % count($userIds)];
            $u2 = $userIds[($ai + 1) % count($userIds)];

            $rows[] = $this->pivotRow($accountId, $u1, $roles[$ai % 3]);
            $rows[] = $this->pivotRow($accountId, $u2, $roles[($ai + 1) % 3]);

            $pairs += 2;
            if ($pairs >= 20) break; // だいたい20件
        }

        foreach ($rows as $r) {
            DB::table('account_user')->updateOrInsert(
                ['account_id' => $r['account_id'], 'user_id' => $r['user_id']],
                $r
            );
        }
    }

    private function pivotRow(int $accountId, int $userId, string $role): array
    {
        return [
            'account_id' => $accountId,
            'user_id' => $userId,
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    // -------------------------
    // skus
    // -------------------------
    private function seedSkus(): array
    {
        // だいたい 2件×5カテゴリ + α
        $rows = [
            // PROC（工程）
            $this->skuRow('PROC_MFD_BASE', 'MFD加工（基本）', 'PROC', ['kind' => 'mfd', 'model' => 'FIXED']),
            $this->skuRow('PROC_MFD_LEN',  'MFD加工（長さ依存）', 'PROC', ['kind' => 'mfd', 'model' => 'PER_MM']),

            // SLEEVE（補強）
            $this->skuRow('SLEEVE_RECOTE', 'リコート', 'SLEEVE', ['material' => 'polymer']),
            $this->skuRow('SLEEVE_REINFORCE', '補強スリーブ', 'SLEEVE', ['material' => 'metal']),
            $this->skuRow('SLEEVE_SUS_PIPE', 'SUSパイプ', 'SLEEVE', ['material' => 'sus']),

            // FIBER（ファイバ）
            $this->skuRow('FIBER_A', '標準ファイバA', 'FIBER', ['mfd' => '9/125', 'minLen' => 50, 'maxLen' => 2000]),
            $this->skuRow('FIBER_B', '標準ファイバB', 'FIBER', ['mfd' => '10/125', 'minLen' => 50, 'maxLen' => 2000]),

            // TUBE（チューブ）
            $this->skuRow('TUBE_X', '保護チューブX', 'TUBE', ['minLen' => 10, 'maxLen' => 2000]),
            $this->skuRow('TUBE_Y', '保護チューブY', 'TUBE', ['minLen' => 10, 'maxLen' => 2000]),

            // CONNECTOR（コネクタ：端子）
            $this->skuRow('CONN_SC_UPC', 'SC/UPCコネクタ', 'CONNECTOR', ['polish' => 'UPC']),
            $this->skuRow('CONN_SC_APC', 'SC/APCコネクタ', 'CONNECTOR', ['polish' => 'APC']),
        ];

        foreach ($rows as $r) {
            DB::table('skus')->updateOrInsert(
                ['sku_code' => $r['sku_code']],
                $r
            );
        }

        return $rows;
    }

    private function skuRow(string $code, string $name, string $category, array $attrs): array
    {
        return [
            'sku_code' => $code,
            'name' => $name,
            'category' => $category,
            'active' => true,
            'attributes' => json_encode($attrs, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    // -------------------------
    // price_books
    // -------------------------
    private function seedPriceBooks(int $n): array
    {
        $existing = DB::table('price_books')->count();
        $need = max(0, $n - $existing);

        if ($need > 0) {
            $rows = [];
            for ($i = 1; $i <= $need; $i++) {
                $idx = $existing + $i;
                $rows[] = [
                    'name' => 'STANDARD',
                    'version' => $idx,
                    'currency' => 'JPY',
                    'valid_from' => now()->toDateString(),
                    'valid_to' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            // unique(name,version)なので insertでOK
            DB::table('price_books')->insert($rows);
        }

        return DB::table('price_books')->orderBy('id')->limit($n)->pluck('id')->all();
    }

    // -------------------------
    // price_book_items
    // -------------------------
    private function seedPriceBookItems(array $priceBookIds, array $skuIdByCode, int $approxN): void
    {
        // だいたい10件。price_book_idとsku_idを適当に組み合わせる
        $skuIds = array_values($skuIdByCode);
        if (count($skuIds) === 0) return;

        $rows = [];
        for ($i = 0; $i < $approxN; $i++) {
            $pbId = $priceBookIds[$i % count($priceBookIds)];
            $skuId = $skuIds[$i % count($skuIds)];

            $model = ['FIXED', 'PER_MM', 'FORMULA'][$i % 3];

            $rows[] = [
                'price_book_id' => $pbId,
                'sku_id' => $skuId,
                'pricing_model' => $model,
                'unit_price' => ($model === 'FIXED') ? (1000 + $i * 50) : null,
                'price_per_mm' => ($model === 'PER_MM') ? (0.8 + $i * 0.01) : null,
                'formula' => ($model === 'FORMULA')
                    ? json_encode(['type' => 'linear', 'base' => 500, 'k' => 1.2], JSON_UNESCAPED_UNICODE)
                    : null,
                'min_qty' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // 重複し得るので updateOrInsert（ただしユニーク制約は無いので本当はinsertでもOK）
        foreach ($rows as $r) {
            DB::table('price_book_items')->insert($r);
        }
    }

    // -------------------------
    // product_templates / product_template_versions
    // -------------------------
    private function seedTemplatesAndVersions(int $n): array
    {
        $templateIds = [];

        for ($i = 1; $i <= $n; $i++) {
            $code = sprintf('TPL_%02d', $i);

            DB::table('product_templates')->updateOrInsert(
                ['template_code' => $code],
                [
                    'name' => "Demo Template {$i}",
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $templateId = DB::table('product_templates')->where('template_code', $code)->value('id');
            $templateIds[] = $templateId;

            // version=1固定で10件（unique(template_id,version)）
            DB::table('product_template_versions')->updateOrInsert(
                ['template_id' => $templateId, 'version' => 1],
                [
                    'dsl_version' => '0.2',
                    'dsl_json' => json_encode([
                        'template_code' => $code,
                        'mfdCount' => ['min' => 1, 'max' => 10],
                        'note' => 'demo dsl',
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // versionのid配列を返す
        return DB::table('product_template_versions')
            ->orderBy('id')
            ->limit($n)
            ->pluck('id')
            ->all();
    }

    // -------------------------
    // configurator_sessions
    // -------------------------
    private function seedConfiguratorSessions(int $n, array $accountIds, array $templateVersionIds): array
    {
        $ids = [];

        for ($i = 0; $i < $n; $i++) {
            $accountId = $accountIds[$i % count($accountIds)];
            $tplVerId  = $templateVersionIds[$i % count($templateVersionIds)];

            $mfdCount = ($i % 5) + 1; // 1..5
            $fiberCount = $mfdCount + 1;

            $fibers = [];
            for ($k = 0; $k < $fiberCount; $k++) {
                $len = 200 + ($k * 50) + ($i * 10);
                $fibers[] = [
                    'skuCode' => ($k % 2 === 0) ? 'FIBER_A' : 'FIBER_B',
                    'lengthMm' => $len,
                    'toleranceMm' => max(1, (int)round($len * 0.01)),
                ];
            }

            $tubes = [];
            $tubeCount = $i % 3; // 0..2
            for ($t = 0; $t < $tubeCount; $t++) {
                $tubes[] = [
                    'skuCode' => ($t % 2 === 0) ? 'TUBE_X' : 'TUBE_Y',
                    'anchor' => ['type' => 'MFD', 'index' => min($mfdCount - 1, $t)],
                    'startOffsetMm' => ($t === 0) ? -10 : 30,
                    'lengthMm' => 150 + $t * 20,
                    'toleranceMm' => null,
                ];
            }

            $config = [
                'mfdCount' => $mfdCount,
                'sleeveSkuCode' => ['SLEEVE_RECOTE', 'SLEEVE_REINFORCE', 'SLEEVE_SUS_PIPE'][$i % 3],
                'fibers' => $fibers,
                'tubeCount' => $tubeCount,
                'tubes' => $tubes,
                'connectors' => [
                    'leftSkuCode' => ($i % 2 === 0) ? 'CONN_SC_UPC' : null,
                    'rightSkuCode' => ($i % 3 === 0) ? 'CONN_SC_APC' : null,
                ],
            ];

            $derived = [
                'fiberCount' => $fiberCount,
            ];

            $validationErrors = []; // 初期は空（必要ならダミーも可）

            $id = DB::table('configurator_sessions')->insertGetId([
                'account_id' => $accountId,
                'template_version_id' => $tplVerId,
                'status' => 'DRAFT',
                'config' => json_encode($config, JSON_UNESCAPED_UNICODE),
                'derived' => json_encode($derived, JSON_UNESCAPED_UNICODE),
                'validation_errors' => json_encode($validationErrors, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $ids[] = $id;
        }

        return $ids;
    }

    // -------------------------
    // quotes / quote_items
    // -------------------------
    private function seedQuotesAndItems(int $n, array $accountIds, array $sessionIds, array $skuIdByCode): array
    {
        $quoteIds = [];

        $skuFiberA = $skuIdByCode['FIBER_A'] ?? null;
        $skuProc   = $skuIdByCode['PROC_MFD_BASE'] ?? null;

        for ($i = 0; $i < $n; $i++) {
            $accountId = $accountIds[$i % count($accountIds)];
            $sessionId = $sessionIds[$i % count($sessionIds)];

            // quote 1件につき item 1〜2件（だいたい10〜20件になる）
            $items = [];

            $subtotal = 0.0;

            if ($skuProc) {
                $qty = 1;
                $unit = 5000 + $i * 100;
                $line = $qty * $unit;
                $subtotal += $line;

                $items[] = [
                    'sku_id' => $skuProc,
                    'quantity' => $qty,
                    'unit_price' => $unit,
                    'line_total' => $line,
                    'options' => json_encode(['type' => 'proc'], JSON_UNESCAPED_UNICODE),
                    'source_path' => '$.proc',
                    'sort_order' => 1,
                ];
            }

            if ($skuFiberA) {
                $qty = 2;
                $unit = 1200 + $i * 10;
                $line = $qty * $unit;
                $subtotal += $line;

                $items[] = [
                    'sku_id' => $skuFiberA,
                    'quantity' => $qty,
                    'unit_price' => $unit,
                    'line_total' => $line,
                    'options' => json_encode(['lengthMm' => 500, 'toleranceMm' => 5], JSON_UNESCAPED_UNICODE),
                    'source_path' => '$.fibers[0]',
                    'sort_order' => 2,
                ];
            }

            $discount = 0.0;
            $tax = round($subtotal * 0.10, 2); // 10%固定ダミー
            $total = $subtotal - $discount + $tax;

            $snapshot = [
                'session_id' => $sessionId,
                'note' => 'demo quote snapshot',
                'pricing' => ['subtotal' => $subtotal, 'tax' => $tax, 'total' => $total],
            ];

            $quoteId = DB::table('quotes')->insertGetId([
                'account_id' => $accountId,
                'session_id' => $sessionId,
                'status' => 'ISSUED',
                'currency' => 'JPY',
                'subtotal' => $subtotal,
                'discount_total' => $discount,
                'tax_total' => $tax,
                'total' => $total,
                'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $quoteIds[] = $quoteId;

            foreach ($items as $it) {
                DB::table('quote_items')->insert([
                    'quote_id' => $quoteId,
                    'sku_id' => $it['sku_id'],
                    'quantity' => $it['quantity'],
                    'unit_price' => $it['unit_price'],
                    'line_total' => $it['line_total'],
                    'options' => $it['options'],
                    'source_path' => $it['source_path'],
                    'sort_order' => $it['sort_order'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return $quoteIds;
    }

    // -------------------------
    // audit_logs
    // -------------------------
    private function seedAuditLogs(int $n, array $userIds, array $quoteIds, array $templateVersionIds, array $skuIdByCode): void
    {
        $rows = [];

        for ($i = 0; $i < $n; $i++) {
            $actor = $userIds[$i % count($userIds)];

            $kind = $i % 3;
            if ($kind === 0) {
                $entityType = 'quote';
                $entityId = $quoteIds[$i % max(1, count($quoteIds))] ?? null;
                $action = 'CREATE_QUOTE';
            } elseif ($kind === 1) {
                $entityType = 'template';
                $entityId = $templateVersionIds[$i % max(1, count($templateVersionIds))] ?? null;
                $action = 'UPDATE_TEMPLATE';
            } else {
                $entityType = 'sku';
                $skuId = $skuIdByCode['FIBER_A'] ?? null;
                $entityId = $skuId;
                $action = 'CREATE_SKU';
            }

            $rows[] = [
                'actor_user_id' => $actor,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'before_json' => null,
                'after_json' => json_encode(['demo' => true, 'i' => $i], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('audit_logs')->insert($rows);
    }
}
```

---

# 2) DatabaseSeeder（データベースシーダー：一括呼び出し）から呼ぶ

`database/seeders/DatabaseSeeder.php` に追記：

```php
public function run(): void
{
    $this->call(DemoDataSeeder::class);
}
```

---

# 3) 実行方法（Docker（ドッカー：コンテナ）環境）

## 3-1) いったん全部作り直して入れ直す（おすすめ）

（ダミーデータを綺麗に入れたい時に一番ラク）

```bash
docker compose exec app php artisan migrate:fresh --seed
```

## 3-2) 既存DBは残してSeederだけ流す

```bash
docker compose exec app php artisan db:seed --class=DemoDataSeeder
```

---

# 4) 入ったか確認（PostgreSQL（ポストグレス：DB））

例：各テーブル件数を見る

```bash
docker compose exec db psql -U mfd -d mfd -c "\
select 'accounts' t, count(*) c from accounts
union all select 'account_user', count(*) from account_user
union all select 'skus', count(*) from skus
union all select 'price_books', count(*) from price_books
union all select 'price_book_items', count(*) from price_book_items
union all select 'product_templates', count(*) from product_templates
union all select 'product_template_versions', count(*) from product_template_versions
union all select 'configurator_sessions', count(*) from configurator_sessions
union all select 'quotes', count(*) from quotes
union all select 'quote_items', count(*) from quote_items
union all select 'audit_logs', count(*) from audit_logs;
"
```

---

# 5) 注意点（初心者ハマりどころ）

* `users` テーブルが「Laravel標準」から変更されている場合
  → `User::create([...])` のカラム（column：列）が合わずにエラーになります。
  そのときは `users` のカラム構成を教えてくれれば、Seederを合わせます。
* `account_user` は複合主キー（primary：主キー）なので、同じ組み合わせを2回入れると失敗します。
  → このSeederは `updateOrInsert`（更新or挿入）で避けています。

---

必要なら次に、**SKU属性（attributes：属性JSON）をもっと現実寄り（例：研磨仕様、MFD、材質、長さ制約）**にして、Configurator（コンフィギュレータ：構成画面）でそのまま選択肢に使える形に整えたSeederにもできます。
