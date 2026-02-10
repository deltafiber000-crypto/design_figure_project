# 2. 管理画面CRUD（運用）を足す手順

縦の1本が通った後、CRUD（基本操作）は **“運用に必要な3つだけ”**から始めるのが安全です。

## 2-1) まず作るCRUD（最小セット）

1. **SKU管理**（skus）

   * category（分類）、active（有効）、attributes（属性JSON）
2. **価格表管理**（price_books / price_book_items）

   * price_book の作成
   * price_book_items の追加（SKU紐付け、pricing_model、単価）
3. **テンプレ/DSL管理**（product_templates / product_template_versions）

   * version を増やす
   * dsl_json を更新（将来の拡張点）

※ configurator_sessions と quotes は、まず **参照中心（編集しない）**が無難です（スナップショットが壊れるため）。










以下は「縦の1本が通った後」に、**運用に必要な3つだけ**のCRUDを安全に足すための**手順**です。日本語で簡潔にまとめました。

**前提（共通方針）**
- `configurator_sessions` と `quotes` は **参照のみ**（編集禁止）。
- 変更履歴を残したいので `audit_logs` へ記録する運用が安全。

---

**2-1) まず作るCRUD（最小セット）**

**A. SKU管理（`skus`）**
1. 一覧/検索/フィルタ  
   - `category`, `active`, `sku_code`, `name`
2. 作成/編集フォーム  
   - `sku_code`（一意）  
   - `name`  
   - `category`（enum）  
   - `active`（bool）  
   - `attributes`（JSONテキスト）
3. バリデーション  
   - `sku_code` 一意  
   - `category` は enum に限定  
   - `attributes` は JSON 形式のみ許可

**B. 価格表管理（`price_books` / `price_book_items`）**
1. `price_books` のCRUD  
   - `name`, `version`, `currency`, `valid_from`, `valid_to`
2. `price_book_items` のCRUD  
   - `price_book_id`  
   - `sku_id`  
   - `pricing_model`（`FIXED / PER_MM / FORMULA`）  
   - `unit_price` / `price_per_mm` / `formula`  
3. バリデーション  
   - `pricing_model` と単価項目の整合チェック  
   - `FORMULA` は **許可型のみ**（例：`linear`）

**C. テンプレ/DSL管理（`product_templates` / `product_template_versions`）**
1. `product_templates` CRUD  
   - `template_code`, `name`, `active`
2. `product_template_versions` CRUD  
   - **version を増やす（新規追加）**  
   - `dsl_version`, `dsl_json`, `active`
3. バリデーション  
   - `dsl_json` は JSON 形式のみ許可  
   - 新版を追加し、既存版は **更新しない運用**推奨

---

**2-2) 画面/ルートの構成方針（最小）**
1. `/admin/skus`  
2. `/admin/price-books`  
3. `/admin/templates`（テンプレとバージョンを別画面でもOK）  

**共通UI**
- 一覧 / 詳細 / 作成 / 編集  
- ページング  
- 検索・フィルタ（`active` / `category` / `name`）

---

**2-3) 安全性のための最低限**
1. Fortify 認証 + 管理権限ガード  
   - `account_user.role` で `admin` のみ許可など
2. 監査ログ（`audit_logs`）  
   - 変更の `before_json` / `after_json` も記録できると安全

---

**2-4) 実装順（推奨）**
1. SKU管理  
2. 価格表管理  
3. テンプレ/DSL管理  
4. 参照画面（`configurator_sessions`, `quotes`）

---

必要なら、この手順を**具体的なコントローラ/ルート/Blade/Livewireの最小実装**まで落とし込みます。