DBセッション保存＋Cookie session_idへ移行（あなたの仕様方針どおり）





了解。ここから **DB（データベース：サーバ側の保存場所）に構成セッションを保存**して、**Cookie（クッキー：ブラウザに保存される小さなデータ）に session_id（セッション識別子：続きから開くための番号）だけ持たせる**形へ移行します。

以下は「初心者でも手を動かせる」手順です（コマンドを打つ場所・ファイル名まで指定します）。

---

# 0) ゴールの動き（完成イメージ）

* ユーザーが `/configurator` を開く

  * Cookieに `config_session_id` が **無い** → DBに新規セッション行を作る → CookieにIDを保存
  * Cookieに `config_session_id` が **ある** → DBからその行を読み込む → 途中から再開
* Livewire（ライブワイヤ：サーバ主導で画面を部分更新する仕組み）で入力が変わるたびに

  * サーバで導出（derived（導出値：自動計算結果））＋検証（validation（入力検証））＋SVG（エスブイジー：ベクター図）生成
  * 同時にDBへ保存（config/derived/errors）

---

# 1) まず確認：テーブル（table（表））がある前提

すでにマイグレーション（migration：DB構造を作る手順）で `configurator_sessions` がある前提で書きます。
（あなたは5-11まで到達しているので、たぶん `php artisan migrate` も済み。）

---

# 2) Model（モデル：DBテーブルを扱うPHPクラス）を作る

## 2-1) ファイル作成

作る場所：`mfd-mvp/app/app/Models/ConfiguratorSession.php`
（`Models` フォルダが無ければ作成）

中身をコピペ：

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ConfiguratorSession extends Model
{
    protected $table = 'configurator_sessions';

    // JSON（ジェイソン：構造データ）を配列（array（配列：リスト/辞書））として扱う
    protected $casts = [
        'config' => 'array',
        'derived' => 'array',
        'validation_errors' => 'array',
    ];

    // 一括代入（mass assignment：まとめて代入）許可
    protected $fillable = [
        'account_id',
        'template_version_id',
        'status',
        'config',
        'derived',
        'validation_errors',
    ];
}
```

---

# 3) Cookie session_id で「作る／読む」を Livewire に入れる

やることは2つです。

1. `mount()`（マウント：画面初期化）で Cookie を見て DB セッションを決める
2. `updatedConfig()`（更新フック：入力が変わった時）で DB に保存する

## 3-1) Livewire コンポーネント（Configurator.php）に「sessionId」を追加

編集する場所：`mfd-mvp/app/app/Livewire/Configurator.php`

### (A) 先頭のuse（ユーズ：読み込み）を追加

```php
use App\Models\ConfiguratorSession;
use Illuminate\Support\Facades\Cookie;
```

### (B) プロパティ（property：変数）を追加

クラスの中に追加：

```php
public ?int $sessionId = null; // DBのconfigurator_sessions.id
```

---

## 3-2) mount() を「Cookie→DB読み込み／無ければ新規作成」に置き換える

`mount(SvgRenderer $renderer)` の中身を、下記の方針にします。

**ポイント**

* Cookie名は固定：`config_session_id`
* もしDBに該当行が無ければ新規作成
* 新規作成したら Cookie::queue（キュー：レスポンスにCookieを載せる予約）で保存

### 置き換えコード（コピペOK）

`mount()` をこの形にしてください（あなたの初期値セットは `defaultConfig()` にまとめます）：

```php
public function mount(SvgRenderer $renderer): void
{
    $cookieName = 'config_session_id';
    $sid = request()->cookie($cookieName);

    $session = null;
    if (is_numeric($sid)) {
        $session = ConfiguratorSession::find((int)$sid);
    }

    if (!$session) {
        // 新規作成（MVPなので account_id/template_version_id は仮で1を入れる）
        $session = ConfiguratorSession::create([
            'account_id' => 1,
            'template_version_id' => 1,
            'status' => 'DRAFT',
            'config' => $this->defaultConfig(),
            'derived' => [],
            'validation_errors' => [],
        ]);

        // CookieにセッションIDを保存（30日）
        Cookie::queue(
            Cookie::make($cookieName, (string)$session->id, 60 * 24 * 30) // 分（minutes：分）
                ->withHttpOnly(true)      // HttpOnly（JSから読めない）
                ->withSameSite('Lax')     // SameSite（別サイトからの送信制限）
        );
    }

    $this->sessionId = $session->id;
    $this->config = $session->config ?? $this->defaultConfig();
    $this->derived = $session->derived ?? [];
    $this->errors = $session->validation_errors ?? [];

    $this->recompute($renderer); // SVGも更新
}
```

### defaultConfig() を追加

Configurator.php の下の方に追加：

```php
private function defaultConfig(): array
{
    return [
        'mfdCount' => 2,
        'sleeveSkuCode' => 'SLEEVE_RECOTE',
        'fibers' => [
            ['skuCode' => 'FIBER_A', 'lengthMm' => 500, 'toleranceMm' => 5],
            ['skuCode' => 'FIBER_A', 'lengthMm' => 300, 'toleranceMm' => 3],
            ['skuCode' => 'FIBER_A', 'lengthMm' => 500, 'toleranceMm' => 5],
        ],
        'tubeCount' => 1,
        'tubes' => [
            [
                'skuCode' => 'TUBE_X',
                'anchor' => ['type' => 'MFD', 'index' => 0],
                'startOffsetMm' => -10,
                'lengthMm' => 200,
                'toleranceMm' => null,
            ],
        ],
        'connectors' => [
            'leftSkuCode' => 'CONN_SC_UPC',
            'rightSkuCode' => null,
        ],
    ];
}
```

---

## 3-3) updatedConfig() の最後で「DB保存」をする

`recompute()` の最後に、DBへ保存する処理を追加します。

### recompute() の末尾に追記（return直前）

```php
// 5) DBへ保存（Cookieのsession_idの行を更新）
if ($this->sessionId) {
    ConfiguratorSession::where('id', $this->sessionId)->update([
        'config' => $this->config,
        'derived' => $this->derived,
        'validation_errors' => $this->errors,
        'status' => 'DRAFT',
    ]);
}
```

> これで「入力変更 → SVG更新 → DB保存」が繋がります。

---

# 4) 重要：account_id / template_version_id の扱い（MVPの簡略）

あなたのテーブル設計だと `configurator_sessions` に `account_id` と `template_version_id` が外部キー（foreign key：別表への参照）で入っているはずです。

MVPでまず動かすなら、簡単な方法は2つあります。

## 方法A（おすすめ）：ダミー行をSeederで1件だけ作る（正攻法）

Seeder（シーダー：初期データ投入）を作って、`accounts` と `product_template_versions` を1件作ります。

### ① Seeder作成（どこで？）

`mfd-mvp`（docker-compose.ymlがある場所）で：

```bash
docker compose exec app php artisan make:seeder MvpBootstrapSeeder
```

### ② Seeder編集

`mfd-mvp/app/database/seeders/MvpBootstrapSeeder.php` を開いて、概略こんな感じで **id=1 を作る**（既にあれば作らない）：

```php
public function run(): void
{
    \DB::table('accounts')->updateOrInsert(
        ['id' => 1],
        ['account_type' => 'B2B', 'name' => 'MVP', 'created_at' => now(), 'updated_at' => now()]
    );

    \DB::table('product_templates')->updateOrInsert(
        ['id' => 1],
        ['template_code' => 'MFD_CONVERSION_FIBER', 'name' => 'MFD MVP', 'active' => true, 'created_at' => now(), 'updated_at' => now()]
    );

    \DB::table('product_template_versions')->updateOrInsert(
        ['id' => 1],
        ['template_id' => 1, 'version' => 1, 'dsl_version' => '0.2', 'dsl_json' => json_encode([]), 'created_at' => now(), 'updated_at' => now()]
    );
}
```

### ③ Seeder実行

```bash
docker compose exec app php artisan db:seed --class=MvpBootstrapSeeder
```

これで `account_id=1` と `template_version_id=1` が使えます。

## 方法B：外部キーをnullable（null許可）にする（早いが設計としては雑）

時間がない時の裏技ですが、先方提出の整合性も考えると **方法Aを推します**。

---

# 5) Cookie session_id が本当に動いているか確認する（初心者向け）

## 5-1) ブラウザでCookie確認

* Chrome（クローム：ブラウザ）

  * 開発者ツール（DevTools：開発者用画面） → Application → Cookies
  * `config_session_id` が入っているか確認

## 5-2) DBに保存されているか確認（psqlコマンド）

`mfd-mvp` でこれを打ちます：

```bash
docker compose exec db psql -U mfd -d mfd -c "select id, status, updated_at, jsonb_pretty(config) from configurator_sessions order by id desc limit 3;"
```

* `config` が入力に応じて変わっていれば成功です。

---

# 6) チューブ開始位置のエラー判定（path設計）を「DB保存と整合」させる

あなたがすでに実装した `validateTubesStartPosition()`（チューブ開始位置検証）に沿って、**保存されるエラー配列（errors）がDBにも入る**ようになりました。

ここでのルール（固定しておくと後が楽）：

* anchor.index範囲外

  * `path = "tubes.{j}.anchor.index"`
* startOffsetMmが数値でない／開始位置が範囲外

  * `path = "tubes.{j}.startOffsetMm"`
* lengthMmが数値でない／終了が範囲外

  * `path = "tubes.{j}.lengthMm"`

この path（パス：どの入力かを示す文字列）が DB の `validation_errors` に入り、
SvgRenderer（SVG生成器）がそれを見て `tube-{j}` を赤くします。

---

# 7) 仕上げ：セッションを「作り直す」ボタン（任意）

Cookieに古い session_id が残ると、検証中に混乱することがあります。
MVPでは「新規セッションを作る」ボタンがあると便利です。

Livewireにメソッド追加：

```php
public function newSession(): void
{
    $cookieName = 'config_session_id';

    $session = ConfiguratorSession::create([
        'account_id' => 1,
        'template_version_id' => 1,
        'status' => 'DRAFT',
        'config' => $this->defaultConfig(),
        'derived' => [],
        'validation_errors' => [],
    ]);

    $this->sessionId = $session->id;
    $this->config = $session->config;
    $this->derived = [];
    $this->errors = [];

    Cookie::queue(
        Cookie::make($cookieName, (string)$session->id, 60 * 24 * 30)
            ->withHttpOnly(true)
            ->withSameSite('Lax')
    );
}
```

Blade（ビュー：画面テンプレート）にボタン追加：

```blade
<button wire:click="newSession" type="button">新規セッション</button>
```

---

## 最短チェックリスト（ここまででOK）

* `/configurator` を開く → Cookieに `config_session_id` が入る
* 入力を変える → SVGが変わる
* ページを更新（リロード：再読み込み） → 入力状態が戻る（DBから復元）
* `psql` で `configurator_sessions.config` が更新されている

---

次にやるなら、DB保存を「毎回update」ではなく

* “変更確定（blur）だけ保存”
* “一定間隔（debounce：連打抑制）で保存”
  にすると負荷が下がりますが、MVPなら今の形で十分です。
