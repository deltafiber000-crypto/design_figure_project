<?php

namespace App\Services;

final class DslEngine
{
    /**
     * @return array{derived: array, errors: array}
     */
    public function evaluate(array $config, array $dsl): array
    {
        $errors = [];

        $mfdCount = (int)($config['mfdCount'] ?? 1);
        $fiberCount = $mfdCount + 1;

        $this->validateRange('mfdCount', $config['mfdCount'] ?? null, $dsl['mfdCount'] ?? null, $errors, 'mfdCount');
        $this->validateRange('tubeCount', $config['tubeCount'] ?? null, $dsl['tubeCount'] ?? null, $errors, 'tubeCount');

        $this->validateFiberCount($config, $fiberCount, $errors);
        $this->validateTubesStartPosition($config, $errors);

        return [
            'derived' => [
                'fiberCount' => $fiberCount,
            ],
            'errors' => $errors,
        ];
    }

    private function validateRange(string $name, mixed $value, mixed $rule, array &$errors, string $path): void
    {
        if (!is_array($rule)) return;
        if (!is_numeric($value)) return;

        $min = $rule['min'] ?? null;
        $max = $rule['max'] ?? null;
        $v = (float)$value;

        if ($min !== null && is_numeric($min) && $v < (float)$min) {
            $errors[] = ['path' => $path, 'message' => "{$name}は".(float)$min."以上です"];
        }
        if ($max !== null && is_numeric($max) && $v > (float)$max) {
            $errors[] = ['path' => $path, 'message' => "{$name}は".(float)$max."以下です"];
        }
    }

    private function validateFiberCount(array $config, int $fiberCount, array &$errors): void
    {
        $fibers = $config['fibers'] ?? [];
        if (!is_array($fibers) || count($fibers) !== $fiberCount) {
            $errors[] = ['path' => 'fibers', 'message' => 'fibers配列の個数が不正です'];
        }
    }

    /**
     * チューブ開始位置のエラー判定（path設計を含む）
     * @return array<int, array{path:string,message:string}>
     */
    private function validateTubesStartPosition(array $config, array &$errors): void
    {
        $mfdCount = (int)($config['mfdCount'] ?? 1);
        $mfdCount = max(1, min(10, $mfdCount));
        $fiberCount = $mfdCount + 1;

        // fiber長さ（未入力に備えた暫定値）
        $fallbackPerSeg = 100.0;
        $fibers = $config['fibers'] ?? [];
        $segLens = [];

        for ($i = 0; $i < $fiberCount; $i++) {
            $len = $fibers[$i]['lengthMm'] ?? null;
            $segLens[$i] = (is_numeric($len) && (float)$len > 0) ? (float)$len : $fallbackPerSeg;
        }

        $totalLen = array_sum($segLens);

        // MFD[k]の位置（mm）= fiber[k]の終端
        $mfdPos = [];
        $cum = 0.0;
        for ($i = 0; $i < $fiberCount; $i++) {
            $cum += $segLens[$i];
            if ($i < $mfdCount) $mfdPos[$i] = $cum;
        }

        $tubes = $config['tubes'] ?? [];
        if (!is_array($tubes)) return;

        foreach ($tubes as $j => $tube) {
            // 1) anchor.index（MFD番号）
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

            // 2) startOffsetMm（±mm）
            $offset = $tube['startOffsetMm'] ?? null;
            if (!is_numeric($offset)) {
                $errors[] = ['path' => "tubes.$j.startOffsetMm", 'message' => 'startOffsetMm（±mm）が数値ではありません'];
                continue;
            }
            $offset = (float)$offset;

            // 3) lengthMm（チューブ長）
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
                $errors[] = ['path' => "tubes.$j.lengthMm", 'message' => "終了位置が範囲外です（0〜{$totalLen}mm）"];
            }
        }
    }
}
