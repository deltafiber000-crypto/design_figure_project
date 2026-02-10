「3) SvgRenderer を先に1本作る」「エラーpathを変えて「どれが赤くなるか」を確認」までできました。「4) ここから Livewire に接続する時の考え方（次の一手）」と「5) 追加：チューブ開始位置のエラー判定（path設計）」のやり方を教えてください





OK、ここからは「**SvgRenderer を Livewire に接続して、入力→サーバ評価→SVG更新**」までを、**手順・ファイル場所・コマンド**込みで説明します。
その後に「**チューブ開始位置のエラー判定（path設計）**」を、**実装できる粒度**で入れます。

---

# A. Livewire に接続する（入力→更新→SVG反映）

やることは大きく3つだけです。

1. Livewire（Laravelの部品）を追加（インストール）
2. Livewireコンポーネント（画面部品）を作る
3. そのコンポーネント内で **SvgRenderer** と **（簡易）Validator（入力検証）** を呼んで、SVGとエラーを更新する

以下、順番に。

---

## A-1) Livewire をインストールする（1回だけ）

### ① どこで？

ターミナル（端末）で、Laravelプロジェクトのルートに移動します。
あなたの構成だとたぶん：

* `mfd-mvp/app/` に `artisan` があるはず

```bash
cd mfd-mvp/app
```

### ② 何を打つ？

**コンテナ内（app）で** Composer（PHPの依存管理）を動かします。

```bash
docker compose exec app composer require livewire/livewire
```

（これで Livewire が Laravel に入ります）

> もし `docker compose exec app` が通らない場合は、いまいるディレクトリが `mfd-mvp` か `mfd-mvp/app` かがズレていることが多いです。
> `docker-compose.yml` があるディレクトリ（mfd-mvp）で `docker compose exec ...` を打つのが基本です。

---

## A-2) Livewire コンポーネントを作る

### ① コマンド

`mfd-mvp`（docker-compose.ymlがある場所）で：

```bash
docker compose exec app php artisan make:livewire Configurator
```

### ② 何ができる？

* PHPクラス：`mfd-mvp/app/app/Livewire/Configurator.php`
* ビュー：`mfd-mvp/app/resources/views/livewire/configurator.blade.php`

（※Livewire v3だとパスや名前が少し違う場合がありますが、作成後の出力ログに出ます）

---

## A-3) ルート（URL）を作って画面を表示する

`mfd-mvp/app/routes/web.php` に追記：

```php
use App\Livewire\Configurator;

Route::get('/configurator', Configurator::class);
```

これで `http://localhost:8080/configurator` で Livewire 画面が開けます。

---

## A-4) Livewire 側で「config を持つ → validate → SVG更新」する

### 1) PHPクラス（Configurator.php）を編集

`mfd-mvp/app/app/Livewire/Configurator.php` を開いて、中身を次の形にします（まずは動く最小版）。

```php
<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\SvgRenderer;

final class Configurator extends Component
{
    public array $config = [];
    public array $derived = [];
    public array $errors = []; // ここはDSLエラー配列（独自形式）

    public string $svg = '';

    public function mount(SvgRenderer $renderer): void
    {
        // 初期値（あとでDB/セッションに置き換える）
        $this->config = [
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

        $this->recompute($renderer);
    }

    // ★ Livewire で config の中身が変わったら呼ぶ（全更新版）
    public function updatedConfig(SvgRenderer $renderer): void
    {
        $this->recompute($renderer);
    }

    private function recompute(SvgRenderer $renderer): void
    {
        // 1) derived（導出）を作る
        $mfdCount = (int)($this->config['mfdCount'] ?? 1);
        $fiberCount = max(1, $mfdCount + 1);
        $this->derived = [
            'fiberCount' => $fiberCount,
        ];

        // 2) defaults（不足配列を埋める）※最低限
        $this->ensureArraySize('fibers', $fiberCount, ['skuCode'=>null,'lengthMm'=>null,'toleranceMm'=>null]);

        $tubeCount = (int)($this->config['tubeCount'] ?? 0);
        $tubeCount = max(0, min($tubeCount, $fiberCount)); // 0..fiberCount に丸める
        $this->config['tubeCount'] = $tubeCount;

        $this->ensureArraySize('tubes', $tubeCount, [
            'skuCode'=>null,
            'anchor'=>['type'=>'MFD','index'=>0],
            'startOffsetMm'=>0,
            'lengthMm'=>null,
            'toleranceMm'=>null
        ]);

        // 3) validate（入力検証）をして errors を作る
        $this->errors = $this->validateConfigForSvg($this->config);

        // 4) SVG生成
        $this->svg = $renderer->render($this->config, $this->derived, $this->errors);
    }

    private function ensureArraySize(string $key, int $size, array $fill): void
    {
        $arr = $this->config[$key] ?? [];
        if (!is_array($arr)) $arr = [];

        $current = count($arr);
        if ($current < $size) {
            for ($i = $current; $i < $size; $i++) $arr[] = $fill;
        } elseif ($current > $size) {
            $arr = array_slice($arr, 0, $size);
        }
        $this->config[$key] = $arr;
    }

    /**
     * SVGに必要な分だけ、パス付きエラーを作る（DSL実装前の暫定Validator）
     * @return array<int, array{path:string,message:string,level?:string}>
     */
    private function validateConfigForSvg(array $config): array
    {
        $errors = [];

        $mfdCount = (int)($config['mfdCount'] ?? 1);
        if ($mfdCount < 1 || $mfdCount > 10) {
            $errors[] = ['path' => 'mfdCount', 'message' => 'mfdCountは1〜10です'];
        }

        $fiberCount = max(1, $mfdCount + 1);
        $fibers = $config['fibers'] ?? [];
        if (!is_array($fibers) || count($fibers) !== $fiberCount) {
            $errors[] = ['path' => 'fibers', 'message' => 'fibers配列の個数が不正です'];
        }

        // チューブ開始位置の検証は Step B で詳述（ここでは呼び出すだけでもOK）
        $tubeErrors = $this->validateTubesStartPosition($config);
        $errors = array_merge($errors, $tubeErrors);

        return $errors;
    }

    /**
     * Step Bで説明する「チューブ開始位置」の検証
     */
    private function validateTubesStartPosition(array $config): array
    {
        // いったん空（次の章で実装を入れる）
        return [];
    }

    public function render()
    {
        return view('livewire.configurator');
    }
}
```

ポイント：

* `updatedConfig()` は **configが変わるたび**に呼ばれ、SVGを更新します。
* 今はDB保存やCookieのsession_idは未接続（次段）。まず画面のリアクティブ動作だけ通します。

---

### 2) ビュー（configurator.blade.php）を編集

`mfd-mvp/app/resources/views/livewire/configurator.blade.php` を開いて、最小でこうします。

```blade
<div style="display:flex; gap:16px; padding:16px;">
    <div style="width: 420px;">
        <h1 style="font-size:18px; font-weight:700;">Configurator（Livewire）</h1>

        <div style="margin-top:12px;">
            <label>mfdCount（1〜10）</label>
            <input type="number" min="1" max="10" wire:model.live="config.mfdCount" style="width:100%;">
        </div>

        <div style="margin-top:12px;">
            <label>tubeCount（0〜fiberCount）</label>
            <input type="number" min="0" wire:model.live="config.tubeCount" style="width:100%;">
        </div>

        <hr style="margin:12px 0;">

        <h2 style="font-weight:700;">Fibers</h2>
        @foreach(($config['fibers'] ?? []) as $i => $f)
            <div style="border:1px solid #ddd; padding:8px; margin-top:8px;">
                <div>fiber[{{ $i }}]</div>
                <label>lengthMm</label>
                <input type="number" wire:model.blur="config.fibers.{{ $i }}.lengthMm" style="width:100%;">
                <label>toleranceMm</label>
                <input type="number" wire:model.blur="config.fibers.{{ $i }}.toleranceMm" style="width:100%;">
            </div>
        @endforeach

        <hr style="margin:12px 0;">

        <h2 style="font-weight:700;">Tubes</h2>
        @foreach(($config['tubes'] ?? []) as $j => $t)
            <div style="border:1px solid #ddd; padding:8px; margin-top:8px;">
                <div>tube[{{ $j }}]</div>

                <label>anchor.index（MFD番号）</label>
                <input type="number" min="0" wire:model.blur="config.tubes.{{ $j }}.anchor.index" style="width:100%;">

                <label>startOffsetMm（MFDからの±mm）</label>
                <input type="number" wire:model.blur="config.tubes.{{ $j }}.startOffsetMm" style="width:100%;">

                <label>lengthMm</label>
                <input type="number" wire:model.blur="config.tubes.{{ $j }}.lengthMm" style="width:100%;">
            </div>
        @endforeach

        <hr style="margin:12px 0;">

        <h2 style="font-weight:700;">Errors</h2>
        <ul>
            @foreach($errors as $e)
                <li><b>{{ $e['path'] ?? '' }}</b>：{{ $e['message'] ?? '' }}</li>
            @endforeach
        </ul>
    </div>

    <div style="flex:1;">
        <h2 style="font-weight:700;">SVG Preview</h2>
        <div style="border:1px solid #ddd; padding:12px;">
            {!! $svg !!}
        </div>
    </div>
</div>
```

ここでわざと使い分けています：

* `wire:model.live`：入力しながら即反映（mfdCountなど軽いもの）
* `wire:model.blur`：入力確定時に反映（長さなど頻繁に変わるもの）

---

### 3) ブラウザで確認

* `http://localhost:8080/configurator`

やってみて：

* mfdCountを変える → fiber本数が変わる／SVGも変わる
* tubeCountを変える → tube編集欄が増減する
* tubeのstartOffsetMmやanchor.indexを変える → tubeの表示位置が変わる

ここまでできたら **Livewire接続は成功**です。

---

# B. チューブ開始位置のエラー判定（path設計）を実装する

次に「validateTubesStartPosition」を実装します。
ここで重要なのは **エラーpathをどこに付けるか**です。

## B-1) path設計（あなたの要望を反映）

チューブ j について、次の3つの検証をします。

1. `anchor.index` が範囲内（0〜mfdCount-1）

* NG → `tubes.{j}.anchor.index`

2. `lengthMm` が正（>0）で、全長制約（任意）を満たす

* NG → `tubes.{j}.lengthMm`

3. `startMm` と `endMm` が全体長（0〜totalLen）に収まる

* startが範囲外 → `tubes.{j}.startOffsetMm`
* endが範囲外 → `tubes.{j}.lengthMm`（もしくは `tubes.{j}` 全体）

このpathにすると、あなたの SvgRenderer は既に

* `tubes.{j}.*` を見て `tube j` を赤くできるので整合します。

---

## B-2) 全体長とMFD位置を計算する（validate内で）

チューブ開始位置は `MFD[index]位置 + startOffsetMm` なので、

* 各fiberのlengthMmを積み上げて
* MFD[k]の位置（mm）を得ます。

> 重要：fiber length未入力でも動く必要がある
> Livewireで編集途中は空があるので、**未入力なら暫定値**で計算します（SvgRendererと同じ考え方）。

---

## B-3) validateTubesStartPosition の実装（コピペOK）

さっき空だった関数を、以下に置き換えます。

`mfd-mvp/app/app/Livewire/Configurator.php` の
`private function validateTubesStartPosition(...)` をこれに：

```php
private function validateTubesStartPosition(array $config): array
{
    $errors = [];

    $mfdCount = (int)($config['mfdCount'] ?? 1);
    $mfdCount = max(1, min(10, $mfdCount));

    $fiberCount = $mfdCount + 1;

    // fiber長さ（未入力に備えて暫定値）
    $fallbackPerSeg = 100.0;
    $fibers = $config['fibers'] ?? [];
    $segLens = [];

    for ($i = 0; $i < $fiberCount; $i++) {
        $len = $fibers[$i]['lengthMm'] ?? null;
        $segLens[$i] = (is_numeric($len) && (float)$len > 0) ? (float)$len : $fallbackPerSeg;
    }

    $totalLen = array_sum($segLens);

    // MFD[k]の位置（mm）は fiber[k]終端
    $mfdPos = [];
    $cum = 0.0;
    for ($i = 0; $i < $fiberCount; $i++) {
        $cum += $segLens[$i];
        if ($i < $mfdCount) $mfdPos[$i] = $cum;
    }

    $tubes = $config['tubes'] ?? [];
    if (!is_array($tubes)) return $errors;

    foreach ($tubes as $j => $tube) {
        // anchor.index
        $aIdx = $tube['anchor']['index'] ?? null;
        if (!is_numeric($aIdx)) {
            $errors[] = ['path' => "tubes.$j.anchor.index", 'message' => 'anchor.index（MFD番号）が数値ではありません'];
            continue;
        }
        $aIdx = (int)$aIdx;
        if ($aIdx < 0 || $aIdx > $mfdCount - 1) {
            $errors[] = ['path' => "tubes.$j.anchor.index", 'message' => "anchor.indexは0〜".($mfdCount-1)."です"];
            continue;
        }

        // startOffsetMm
        $offset = $tube['startOffsetMm'] ?? null;
        if (!is_numeric($offset)) {
            $errors[] = ['path' => "tubes.$j.startOffsetMm", 'message' => 'startOffsetMm（±mm）が数値ではありません'];
            continue;
        }
        $offset = (float)$offset;

        // lengthMm
        $lenMm = $tube['lengthMm'] ?? null;
        if (!is_numeric($lenMm)) {
            $errors[] = ['path' => "tubes.$j.lengthMm", 'message' => 'チューブ長さが数値ではありません'];
            continue;
        }
        $lenMm = (float)$lenMm;
        if ($lenMm <= 0) {
            $errors[] = ['path' => "tubes.$j.lengthMm", 'message' => 'チューブ長さは0より大きくしてください'];
            continue;
        }

        // 開始・終了（mm）
        $anchorMm = $mfdPos[$aIdx] ?? 0.0;
        $startMm = $anchorMm + $offset;
        $endMm = $startMm + $lenMm;

        // 範囲チェック（0..totalLen）
        if ($startMm < 0 || $startMm > $totalLen) {
            $errors[] = ['path' => "tubes.$j.startOffsetMm", 'message' => "開始位置が範囲外です（0〜{$totalLen}mm）"];
        }
        if ($endMm < 0 || $endMm > $totalLen) {
            // endが範囲外はlengthに紐付けた方が直感的
            $errors[] = ['path' => "tubes.$j.lengthMm", 'message' => "終了位置が範囲外です（0〜{$totalLen}mm）"];
        }
    }

    return $errors;
}
```

---

## B-4) 動作確認（どこが赤くなるか）

`/configurator` 画面で次を試します。

* `anchor.index` を `-1` や `mfdCount` にする
  → エラーpath `tubes.0.anchor.index` が出る
  → tube[0] が赤くなる（SvgRendererのルール通り）

* `startOffsetMm` を大きくして開始が全長を超える（例：+99999）
  → `tubes.0.startOffsetMm` が出る
  → tube[0] が赤くなる

* `lengthMm` を大きくして終了が全長を超える
  → `tubes.0.lengthMm` が出る
  → tube[0] が赤くなる

---

# C. 次の一手（あなたが今やりやすい順）

ここまでできたら、次は2つの方向があります。どちらも今の実装に自然に乗ります。

1. **“±誤差の自動算出”を recompute に入れる**（未入力なら自動埋め）
2. **DBセッション保存＋Cookie session_id**へ移行（あなたの仕様方針どおり）

ただ、あなたの目的は「SVGを核にUI検討を先方に出す」なので、まずは

* tubesの開始位置（MFD基準±mm）
* エラーがSVGに反映される
  が通った今の状態で十分に説得力があります。

---

必要なら、次のメッセージで「チューブが複数で、開始位置がMFDをまたぐケース（例：MFD[0]-10mmから長さ200mmで、fiber[0]とfiber[1]を跨ぐ）」を想定して、**SVG上の“跨ぎ表示”（区間またぎ）をどう描画するか**のルールも具体化できます。今のSvgRendererは「全体長に対して1本の矩形」で表現しているので、跨ぎは自然に表現できますが、「区間境界で見た目を分けたい」なら拡張できます。
