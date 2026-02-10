確認すべき仕様の穴の補足と、急遽変更された技術仕様を以下に示します。また、正直jsonファイルやsqlコマンド群だけ渡されても何をどうすればいいかわからないくらいには初心者なので、具体的にエディタやDockerなど何をどう操作してどこにファイルやコンテナを作り、どこにコマンドを打ち込めばいいかなどまで詳細に教えてください。

◎確認すべき仕様の穴の補足
・「mfdCount の取り得る値」：1～10
・「補強スリーブのSKU候補（sku_code）」：リコート・補強スリーブ・SUSパイプの3種類(SKU)
・「長さ制約（Min/Max：最小/最大）」：全ファイバ共通
・「どのfiber区間にチューブを付けるか」：ユーザー選択
・「コネクタ研磨仕様（Polish Spec：端面仕様）」：SKUに内包（コネクタ種類選択時に決定）
・「MFD変換（加工工程）の単価」： n だけでなくファイバ種類や長さも影響
・「±誤差」：長さに対して自動算出、一応ユーザーが変更できるようにする

◎急遽変更された技術仕様（さらなるMVPを模索した結果、MVP完成後に実装する可能性あり）
・BackEnd言語とフレームワークを「Java 21 + Spring Boot」から「PHP + Laravel」に変更
・フロント側キャッシュを「TanStack Query」から「Cookie」に変更
・「Cache：Redis」を不採用
・「Search：Meilisearch」を不採用




了解です。いただいた補足で、**仕様（要件（何を作るか））がかなり明確**になりました。
また、技術仕様（技術スタック（使う道具一式））が **PHP + Laravel（PHPフレームワーク（開発基盤））** に変わったので、**初心者でも迷いにくい「手順込み」**で整理し直します。

以下では、

1. **ここから何をすべきか（最短の順番）**
2. **あなたの仕様を反映した DSL仕様書（JSON（データ形式））の一例**
3. **DBスキーマ（データベース構造）の一例（Laravelマイグレーション（DB定義コード）で提示）**
4. **エディタ（編集ソフト）とDocker（コンテナ実行環境）で “どこで何をどう操作するか” の手順**

を、できるだけ具体的に書きます。

---

# 1. まずの結論：次にやるのは「動く骨格（最小動作）」作り

あなたが今いる地点（スロット定義と制約が言語化できた）から最短で前に進むには、次の“縦の一本（ウォーキングスケルトン（最小動作骨格））”を作ります。

✅ **縦の一本（最小）**

* 入力：`mfdCount（1〜10）` と各パーツ選択＋長さ
* 処理：制約チェック（バリデーション（入力検証））＋BOM生成（部品表生成）＋価格計算（Pricing（価格ロジック））
* 出力：見積保存（Quote（見積）スナップショット（固定記録））

これが動けば、UI（ユーザーインターフェース（画面））の改善や検索強化は後から積み上げられます。

---

# 2. 技術仕様変更を踏まえた「MVP構成（最小構成）」

あなたの変更点（Redis（高速キャッシュ）なし、Meilisearch（検索エンジン）なし、フロントキャッシュはCookie（ブラウザ保存））に合わせて、MVP（Minimum Viable Product（最小実用））はこうするのが安全です。

## 2-1. 今回のMVPのおすすめ

* Backend（バックエンド（サーバ処理））：**Laravel（PHPフレームワーク）**
* DB（データベース（データ保管））：**PostgreSQL（関係DB）**
* Webサーバ（配信サーバ）：**Nginx（Webサーバ）**
* コンテナ（隔離実行環境）：**Docker（コンテナ実行環境） + docker compose（複数コンテナ起動）**
* 状態保持：**Cookie（ブラウザ保存）には “セッションID（識別番号）だけ” を入れる**

  * ※構成データ全部をCookieに入れると容量制限（一般に数KB）がすぐ来るので、**DBに保存してCookieはキーだけ**が現実的です。

## 2-2. Search（検索）をどうするか（Meilisearch不採用の代替）

MVPでは「検索エンジンなし」なので、SKU候補はまず **DBの絞り込み**で十分です。

* 例：カテゴリ（分類）＝`FIBER` のSKUだけを一覧し、必要なら `sku_code` / `name` を部分一致（ILIKE（大文字小文字無視の部分一致））で絞る
* 属性（attributes（属性JSON））は JSONB（JSON型）で持ち、必要ならインデックス（Index（索引））を追加

---

# 3. DSL仕様書（案） v0.2（あなたの補足を反映）

あなたの要件を素直に実装できるように、DSL（Domain-Specific Language（業務特化の定義形式））を **「入力スキーマ（入力の形）＋導出（自動計算）＋制約（検証）＋BOM生成」** に分けます。

ここで重要なのは、**±誤差（toleranceMm）は “自動算出で初期値を入れるが、ユーザーが上書きできる”** なので、DSLには `defaults（初期値適用）` を入れます。

---

## 3-1. コンフィグ（Config（構成データ））の形（正本）

MFD変換個数 n が決まると fiberCount = n+1 になる前提で、構成はこうします（例）。

```json
{
  "mfdCount": 2,
  "sleeveSkuCode": "SLEEVE_RECOTE",
  "fibers": [
    { "skuCode": "FIBER_A", "lengthMm": 500, "toleranceMm": 5 },
    { "skuCode": "FIBER_A", "lengthMm": 300, "toleranceMm": 3 },
    { "skuCode": "FIBER_A", "lengthMm": 500, "toleranceMm": 5 }
  ],
  "tubeCount": 1,
  "tubes": [
    { "skuCode": "TUBE_X", "targetFiberIndex": 1, "lengthMm": 200, "toleranceMm": 2 }
  ],
  "connectors": {
    "leftSkuCode": "CONN_SC_UPC",
    "rightSkuCode": null
  }
}
```

* 研磨仕様（Polish Spec（端面仕様））は **SKUに内包**なので `polishSpec` は持たない
* `skuId` ではなく `skuCode` にしているのは、MVPでは扱いやすいからです（DBではskuCodeをunique（重複禁止）にします）

---

## 3-2. DSL仕様書（JSON）一例（そのままファイルにできる）

以下が **DSL v0.2 の“たたき台”**です。
（長さ上限などは「全ファイバ共通」とのことなので constants（定数）で固定にしています。）

> ※ `tolerance` の自動算出式は仮で「長さの1%を切り上げ、最小1mm、最大20mm」にしています。実際の現場ルールに合わせて変更してください。

```json
{
  "dslVersion": "0.2",
  "templateCode": "MFD_CONVERSION_FIBER",
  "constants": {
    "fiberLengthMinMm": 1,
    "fiberLengthMaxMm": 5000,
    "tubeLengthMinMm": 1,
    "tubeLengthMaxMm": 5000,

    "toleranceMinMm": 0,
    "toleranceMaxMm": 20,
    "toleranceDefaultMinMm": 1,
    "toleranceRate": 0.01
  },

  "schema": {
    "mfdCount": {
      "type": "enum",
      "values": [1,2,3,4,5,6,7,8,9,10],
      "facet": true,
      "description": "MFD変換個数（1〜10）"
    },

    "sleeveSkuCode": {
      "type": "skuCode",
      "skuCategory": "SLEEVE",
      "facet": true,
      "allowedSkuCodes": ["SLEEVE_RECOTE", "SLEEVE_REINFORCE", "SLEEVE_SUS_PIPE"],
      "description": "補強スリーブ（3種類）"
    },

    "fibers": {
      "type": "array",
      "description": "ファイバ区間（n+1個）",
      "item": {
        "skuCode": { "type": "skuCode", "skuCategory": "FIBER", "facet": true },
        "lengthMm": { "type": "number", "unit": "mm" },
        "toleranceMm": { "type": "number", "unit": "mm", "nullable": true }
      }
    },

    "tubeCount": {
      "type": "integer",
      "min": 0,
      "description": "チューブ個数（0〜ファイバ個数）"
    },

    "tubes": {
      "type": "array",
      "description": "チューブ（ユーザーがどのファイバ区間か選ぶ）",
      "item": {
        "skuCode": { "type": "skuCode", "skuCategory": "TUBE", "facet": true },
        "targetFiberIndex": { "type": "integer", "min": 0 },
        "lengthMm": { "type": "number", "unit": "mm" },
        "toleranceMm": { "type": "number", "unit": "mm", "nullable": true }
      }
    },

    "connectors": {
      "type": "object",
      "properties": {
        "leftSkuCode":  { "type": "skuCodeOrNull", "skuCategory": "CONNECTOR", "facet": true },
        "rightSkuCode": { "type": "skuCodeOrNull", "skuCategory": "CONNECTOR", "facet": true }
      },
      "description": "コネクタは0〜2個（両端）"
    }
  },

  "derived": [
    {
      "id": "fiberCount",
      "target": "$.derived.fiberCount",
      "expr": { "op": "add", "args": [ { "var": "$.mfdCount" }, 1 ] },
      "description": "ファイバ個数 = mfdCount + 1"
    },
    {
      "id": "totalFiberLengthMm",
      "target": "$.derived.totalFiberLengthMm",
      "expr": { "op": "sum", "args": [ { "var": "$.fibers[*].lengthMm" } ] },
      "description": "全ファイバ長さ合計"
    },
    {
      "id": "connectorCount",
      "target": "$.derived.connectorCount",
      "expr": {
        "op": "add",
        "args": [
          { "op": "if", "args": [ { "op": "isNull", "args": [ { "var": "$.connectors.leftSkuCode" } ] }, 0, 1 ] },
          { "op": "if", "args": [ { "op": "isNull", "args": [ { "var": "$.connectors.rightSkuCode" } ] }, 0, 1 ] }
        ]
      },
      "description": "コネクタ個数"
    }
  ],

  "defaults": [
    {
      "id": "ensure_fibers_size",
      "type": "ensure_array_size",
      "target": "$.fibers",
      "sizeExpr": { "var": "$.derived.fiberCount" },
      "fill": { "skuCode": null, "lengthMm": null, "toleranceMm": null },
      "description": "mfdCount変更時にfibers配列をn+1に合わせる（不足分は空で追加）"
    },
    {
      "id": "ensure_tubes_size",
      "type": "ensure_array_size",
      "target": "$.tubes",
      "sizeExpr": { "var": "$.tubeCount" },
      "fill": { "skuCode": null, "targetFiberIndex": 0, "lengthMm": null, "toleranceMm": null },
      "description": "tubeCount変更時にtubes配列を合わせる"
    },
    {
      "id": "default_fiber_tolerance",
      "type": "for_each",
      "target": "$.fibers",
      "as": "item",
      "apply": {
        "type": "set_if_null",
        "field": "toleranceMm",
        "valueExpr": {
          "op": "clamp",
          "args": [
            {
              "op": "max",
              "args": [
                { "var": "$.constants.toleranceDefaultMinMm" },
                { "op": "ceil", "args": [ { "op": "mul", "args": [ { "var": "$.item.lengthMm" }, { "var": "$.constants.toleranceRate" } ] } ] }
              ]
            },
            { "var": "$.constants.toleranceMinMm" },
            { "var": "$.constants.toleranceMaxMm" }
          ]
        }
      },
      "description": "ファイバ±誤差の初期値を長さから自動算出（ユーザーが上書き可能）"
    },
    {
      "id": "default_tube_tolerance",
      "type": "for_each",
      "target": "$.tubes",
      "as": "item",
      "apply": {
        "type": "set_if_null",
        "field": "toleranceMm",
        "valueExpr": {
          "op": "clamp",
          "args": [
            {
              "op": "max",
              "args": [
                { "var": "$.constants.toleranceDefaultMinMm" },
                { "op": "ceil", "args": [ { "op": "mul", "args": [ { "var": "$.item.lengthMm" }, { "var": "$.constants.toleranceRate" } ] } ] }
              ]
            },
            { "var": "$.constants.toleranceMinMm" },
            { "var": "$.constants.toleranceMaxMm" }
          ]
        }
      },
      "description": "チューブ±誤差の初期値を長さから自動算出（ユーザーが上書き可能）"
    }
  ],

  "rules": [
    {
      "id": "fiber_count_rule",
      "type": "cardinality_equals",
      "target": "$.fibers",
      "valueExpr": { "var": "$.derived.fiberCount" },
      "message": "ファイバ個数は mfdCount + 1 です"
    },

    {
      "id": "fiber_length_range",
      "type": "for_each",
      "target": "$.fibers",
      "as": "item",
      "check": {
        "type": "number_between",
        "targetExpr": { "var": "$.item.lengthMm" },
        "minExpr": { "var": "$.constants.fiberLengthMinMm" },
        "maxExpr": { "var": "$.constants.fiberLengthMaxMm" }
      },
      "message": "ファイバ長さが範囲外です"
    },

    {
      "id": "tube_count_range",
      "type": "number_between",
      "target": "$.tubeCount",
      "minExpr": 0,
      "maxExpr": { "var": "$.derived.fiberCount" },
      "message": "チューブ個数は 0〜ファイバ個数 です"
    },

    {
      "id": "tubes_count_rule",
      "type": "cardinality_equals",
      "target": "$.tubes",
      "valueExpr": { "var": "$.tubeCount" },
      "message": "tubes配列の個数は tubeCount と一致させてください"
    },

    {
      "id": "tube_target_index_range",
      "type": "for_each",
      "target": "$.tubes",
      "as": "item",
      "check": {
        "type": "number_between",
        "targetExpr": { "var": "$.item.targetFiberIndex" },
        "minExpr": 0,
        "maxExpr": { "op": "sub", "args": [ { "var": "$.derived.fiberCount" }, 1 ] }
      },
      "message": "チューブの装着先（targetFiberIndex）が範囲外です"
    },

    {
      "id": "connector_count_rule",
      "type": "number_between",
      "target": "$.derived.connectorCount",
      "minExpr": 0,
      "maxExpr": 2,
      "message": "コネクタは0〜2個（両端）です"
    },

    {
      "id": "sleeve_required",
      "type": "required",
      "target": "$.sleeveSkuCode",
      "message": "補強スリーブ種類を選択してください"
    }
  ],

  "bom": [
    {
      "id": "bom_process_mfd",
      "type": "addItem",
      "skuCode": "PROC_MFD_CONVERSION",
      "qtyExpr": { "var": "$.mfdCount" },
      "options": {
        "mfdCount": { "var": "$.mfdCount" },
        "totalFiberLengthMm": { "var": "$.derived.totalFiberLengthMm" }
      },
      "description": "MFD変換加工（価格はmfdCount・ファイバ種類・長さに依存）"
    },

    {
      "id": "bom_sleeve",
      "type": "addItem",
      "skuCodeExpr": { "var": "$.sleeveSkuCode" },
      "qtyExpr": { "var": "$.mfdCount" },
      "options": {},
      "description": "補強スリーブ（仮：mfdCount個）"
    },

    {
      "id": "bom_fibers",
      "type": "addItemsFromArray",
      "array": "$.fibers",
      "skuCodeField": "skuCode",
      "qtyExpr": 1,
      "optionsMap": {
        "lengthMm": "$.item.lengthMm",
        "toleranceMm": "$.item.toleranceMm"
      },
      "sourcePathTemplate": "$.fibers[{index}]",
      "description": "ファイバ区間をBOM行へ"
    },

    {
      "id": "bom_tubes",
      "type": "addItemsFromArray",
      "array": "$.tubes",
      "skuCodeField": "skuCode",
      "qtyExpr": 1,
      "optionsMap": {
        "targetFiberIndex": "$.item.targetFiberIndex",
        "lengthMm": "$.item.lengthMm",
        "toleranceMm": "$.item.toleranceMm"
      },
      "sourcePathTemplate": "$.tubes[{index}]",
      "description": "チューブをBOM行へ"
    },

    {
      "id": "bom_left_connector",
      "type": "addItemIfNotNull",
      "field": "$.connectors.leftSkuCode",
      "skuCodeExpr": { "var": "$.connectors.leftSkuCode" },
      "qtyExpr": 1,
      "options": {},
      "sourcePath": "$.connectors.leftSkuCode",
      "description": "左端コネクタ（研磨仕様はSKUに内包）"
    },

    {
      "id": "bom_right_connector",
      "type": "addItemIfNotNull",
      "field": "$.connectors.rightSkuCode",
      "skuCodeExpr": { "var": "$.connectors.rightSkuCode" },
      "qtyExpr": 1,
      "options": {},
      "sourcePath": "$.connectors.rightSkuCode",
      "description": "右端コネクタ（研磨仕様はSKUに内包）"
    }
  ]
}
```

---

# 4. DBスキーマ一例（Laravelマイグレーションで出します）

「SQLコマンド群（SQL DDL（DB定義））」より、Laravelでは **マイグレーション（DBの構造をコードで管理）**で作るのが分かりやすいです。
あなたが迷いにくいよう、**“コピペで動く”一枚マイグレーション例**にします。

---

## 4-1. 作るテーブル（最低限）

* `accounts`（顧客（B2B/B2C））
* `account_user`（顧客とユーザーの紐付け）
* `skus`（SKUマスタ）
* `price_books`（価格表）
* `price_book_items`（価格表の明細）
* `product_templates`（製品テンプレート）
* `product_template_versions`（テンプレートのDSL版）
* `configurator_sessions`（作業セッション）
* `quotes`（見積）
* `quote_items`（見積明細）
* `audit_logs`（監査ログ）

---

## 4-2. Laravelマイグレーション（例）

後で手順で「このファイルをどこに作るか」も書きます。今は中身だけ提示します。

```php
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
```

---

# 5. ここから「実際に手を動かす」超具体手順

ここからが「エディタ（編集ソフト）とDocker（コンテナ実行環境）をどう操作するか」です。
OS（オペレーティングシステム（PCの基本ソフト））が不明なので、**Windows（ウィンドウズ（OS））はWSL2（Linux環境）推奨**、Mac（マック（OS））/Linux（リナックス（OS））はそのまま進められる形にします。

---

## 5-0. 事前に入れるもの（インストール（導入））

* Docker Desktop（DockerのGUIアプリ（操作アプリ））
* VS Code（エディタ（編集ソフト））
* Git（バージョン管理（履歴管理））※任意だが強く推奨

Windowsの場合：

* WSL2（Windows上でLinuxを動かす仕組み）＋Ubuntu（Linux配布版（OS））

---

## 5-1. フォルダ（ディレクトリ（入れ物））を作る

まず作業用フォルダを作ります。
ここからのコマンド（命令）は、**ターミナル（端末（コマンド入力画面））**で実行します。

```bash
mkdir mfd-mvp
cd mfd-mvp
mkdir app docker docker/nginx docker/php
```

---

## 5-2. docker-compose.yml（複数コンテナ定義）を作る

VS Code（エディタ）で `mfd-mvp` フォルダを開きます。

* VS Code を開く
* 「フォルダを開く」→ `mfd-mvp` を選ぶ
* ルート（最上位）に `docker-compose.yml` を新規作成

中身をこれにします（コピペ）：

```yaml
services:
  app:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    volumes:
      - ./app:/var/www/html
    depends_on:
      - db

  web:
    image: nginx:alpine
    ports:
      - "8080:80"
    volumes:
      - ./app:/var/www/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - app

  db:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB: mfd
      POSTGRES_USER: mfd
      POSTGRES_PASSWORD: mfd
    ports:
      - "5432:5432"
    volumes:
      - dbdata:/var/lib/postgresql/data

volumes:
  dbdata:
```

---

## 5-3. PHP用Dockerfile（PHP実行環境）を作る

`docker/php/Dockerfile` を作って、これを貼ります。

```dockerfile
FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git unzip libpq-dev libzip-dev \
    && docker-php-ext-install pdo_pgsql zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
```

* `pdo_pgsql`（PostgreSQL接続拡張）を入れるのが重要です

---

## 5-4. Nginx設定ファイル（Web配信設定）を作る

`docker/nginx/default.conf` を作って、これを貼ります。

```nginx
server {
  listen 80;
  server_name _;
  root /var/www/html/public;

  index index.php index.html;

  location / {
    try_files $uri $uri/ /index.php?$query_string;
  }

  location ~ \.php$ {
    try_files $uri =404;
    fastcgi_pass app:9000;
    fastcgi_index index.php;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
  }

  location ~ /\.ht {
    deny all;
  }
}
```

---

## 5-5. Laravelプロジェクト（アプリ本体）を作る

ここからはターミナル（端末）で、`mfd-mvp` 直下（ルート）にいる状態で実行します。

### Mac/Linux/WSL（bash系）なら：

```bash
docker run --rm -v "$(pwd)":/work -w /work composer:2 create-project laravel/laravel app
```

### Windows PowerShell（パワーシェル（端末））なら：

```powershell
docker run --rm -v "${PWD}:/work" -w /work composer:2 create-project laravel/laravel app
```

これで `mfd-mvp/app` の中に Laravel（フレームワーク）一式が生成されます。

---

## 5-6. コンテナ（隔離実行環境）を起動する

```bash
docker compose up -d --build
```

* `--build`（ビルド（組み立て））は最初だけ必要
* これで `app`（PHP）/`web`（Nginx）/`db`（Postgres）の3つのコンテナが起動します

---

## 5-7. Laravelの設定（.env（環境設定ファイル））をDBに合わせる

`app/.env` を VS Code で開いて、以下を探して書き換えます（なければ追記）。

```env
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=mfd
DB_USERNAME=mfd
DB_PASSWORD=mfd
```

---

## 5-8. アプリ鍵（APP_KEY（暗号用鍵））を生成する

```bash
docker compose exec app php artisan key:generate
```

* `php artisan`（Laravel付属コマンド）を **コンテナ内（app）で実行**している、という意味です
* `docker compose exec app ...` は「appコンテナの中でコマンドを実行」という操作

---

## 5-9. マイグレーション（DB定義）を作って流す

### ① マイグレーションファイルを作る

```bash
docker compose exec app php artisan make:migration create_core_tables
```

すると `app/database/migrations/xxxx_xx_xx_xxxxxx_create_core_tables.php` ができます。

### ② そのファイルを開いて中身を置き換える

さっきの **4-2のマイグレーション例**を、そのファイルに貼り付けて保存します。

### ③ DBに反映（マイグレート（DB反映））

```bash
docker compose exec app php artisan migrate
```

---

## 5-10. DSLファイルを置く場所（どこに保存するか）

まずはファイルとして管理し、あとでDBに登録（テンプレート版に保存）するのがおすすめです。

* 作るフォルダ：`app/storage/app/dsl/`
* 作るファイル：`app/storage/app/dsl/MFD_CONVERSION_FIBER.v0.2.json`

VS Codeで上記パスにファイルを作り、**3-2のDSL JSON**を貼り付けて保存します。

---

## 5-11. 動作確認（ブラウザで開く）

ブラウザ（Chrome等）で：

* `http://localhost:8080`

にアクセスします。

Laravelの初期画面が出れば、**コンテナ起動とWeb配信は成功**です。

---

# 6. 次に実装する最小コード（「何を書けばいいか」）

ここまでで「環境（Docker）」「DB（マイグレーション）」「DSLファイル」が揃いました。
次の最小実装はこの3つです。

## 6-1. SKUマスタ（最低限）をDBに入れる（シード（初期投入））

* `PROC_MFD_CONVERSION`
* `SLEEVE_RECOTE / SLEEVE_REINFORCE / SLEEVE_SUS_PIPE`
* ファイバSKUを数種類
* チューブSKUを数種類
* コネクタSKU（研磨仕様込みSKU）を数種類（例：`CONN_SC_UPC`）

これを入れるために **Seeder（シーダー（初期データ投入））** を作ります。

```bash
docker compose exec app php artisan make:seeder DemoSkuSeeder
```

作られた `app/database/seeders/DemoSkuSeeder.php` に、SKUを作るコードを書いて、

```bash
docker compose exec app php artisan db:seed --class=DemoSkuSeeder
```

で投入します。

（必要なら、あなたのSKU候補の表を貼ってもらえれば、Seederコードを具体的に作って返します）

---

## 6-2. セッション（作業状態）を作ってCookieにIDを保存

Cookie（ブラウザ保存）には **session_id（作業セッション番号）だけ**を入れます。

* DB：`configurator_sessions` に `config` を保存
* ブラウザ：Cookieに `config_session_id` を保存

これで「フロントキャッシュをCookieへ」の方針に合います（“実体はDB、キーはCookie”）。

---

## 6-3. DSLエンジン（ルール評価＋BOM生成）をPHPで1つ作る

`app/app/Services/DslEngine.php` のようなファイルを作り、

* 入力：DSL JSON + config
* 出力：derived + validation_errors + bomItems
  を返す関数を作ります。

ここができると、UIが多少ダサくても「見積まで通る」ようになります。

---

# 7. 重要な注意（初心者向け）

* Redis（キャッシュ）は無いので、**状態はDBに持つ**のが安全
* Search（検索エンジン）なしでも、MVPは **SKU数を絞って**十分試作できる
* “MISUMIみたいな検索”は後から Meilisearch（検索エンジン）やOpenSearch（検索エンジン）を足す段階で対応すればOK

---

# 次にあなたが貼ると、こちらで「Seeder＋最小画面」まで具体化できます

あなたがもし可能なら、次のどれかを貼ってください（全部じゃなくてOKです）。

1. SKU候補の一覧（sku_code、カテゴリ、名前、必要な属性（例：コネクタ研磨仕様など））
2. ファイバ種類の候補（列挙）と「MFD変換工程の価格に影響する属性（何が難易度になるか）」の仮ルール
3. 長さ上限（fiberLengthMaxMm、tubeLengthMaxMm）を実際の値で確定

貼ってもらえれば、

* DSLの `constants` を確定
* `DemoSkuSeeder.php` の具体コード
* セッション作成→入力フォーム→見積発行の最小ルート（routes/controller/view）
  まで、一気に“動く形”として出せます。
