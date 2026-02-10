<?php
use Illuminate\Support\Facades\Route;
use App\Services\SvgRenderer;
use App\Livewire\Configurator;
use Illuminate\Http\Request;
use App\Models\ConfiguratorSession;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Admin\AdminSkuController;
use App\Http\Controllers\Admin\AdminPriceBookController;
use App\Http\Controllers\Admin\AdminPriceBookItemController;
use App\Http\Controllers\Admin\AdminTemplateController;
use App\Http\Controllers\Admin\AdminTemplateVersionController;
use App\Http\Controllers\Admin\AdminChangeRequestController;
use App\Http\Controllers\Admin\AdminAuditLogController;
use App\Http\Controllers\Ops\ConfiguratorSessionController;
use App\Http\Controllers\Ops\QuoteController;
use App\Services\SnapshotPdfService;

Route::get('/', function () {
    return view('landing');
});

Route::get('/configurator', Configurator::class);

Route::post('/configurator/autosave', function (Request $request) {

    $sid = (int)$request->input('session_id');

    // Cookie（保存）と一致しないsidを拒否（簡易防御）
    $cookieSid = (int)$request->cookie('config_session_id');
    if ($cookieSid !== $sid) {
        abort(403);
    }

    $configJson = (string)$request->input('config_json', '{}');
    $config = json_decode($configJson, true);
    if (!is_array($config)) $config = [];

    // ここでは「configだけ」保存（derived/errorsは次回表示時に再計算でOK）
    ConfiguratorSession::where('id', $sid)->update([
        'config' => $config,
        'status' => 'DRAFT',
    ]);

    return response()->noContent(); // 空でOK
})->name('configurator.autosave');

Route::get('/quotes/{id}', function ($id, SvgRenderer $renderer) {
    $quote = DB::table('quotes')->where('id', (int)$id)->first();
    if (!$quote) {
        abort(404);
    }

    $snapshot = json_decode($quote->snapshot ?? '', true);
    if (!is_array($snapshot)) $snapshot = [];

    $config = $snapshot['config'] ?? [];
    $derived = $snapshot['derived'] ?? [];
    $errors = $snapshot['validation_errors'] ?? [];

    $svg = $renderer->render($config, $derived, $errors);

    $totals = $snapshot['totals'] ?? [
        'subtotal' => (float)($quote->subtotal ?? 0),
        'tax' => (float)($quote->tax_total ?? 0),
        'total' => (float)($quote->total ?? 0),
    ];

    return view('quotes.show', [
        'quote' => $quote,
        'snapshot' => $snapshot,
        'svg' => $svg,
        'totals' => $totals,
    ]);
})->middleware('auth')->name('quotes.show');

Route::get('/quotes/{id}/snapshot.pdf', function ($id, SvgRenderer $renderer, SnapshotPdfService $pdfService) {
    $quote = DB::table('quotes')->where('id', (int)$id)->first();
    if (!$quote) {
        abort(404);
    }

    $snapshot = json_decode($quote->snapshot ?? '', true);
    if (!is_array($snapshot)) $snapshot = [];

    $config = $snapshot['config'] ?? [];
    $derived = $snapshot['derived'] ?? [];
    $errors = $snapshot['validation_errors'] ?? [];

    $svg = $renderer->render($config, $derived, $errors);

    return $pdfService->download(
        '見積 スナップショット',
        $svg,
        "quote_{$id}_snapshot.pdf"
    );
})->middleware('auth')->name('quotes.snapshot.pdf');

Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/skus', [AdminSkuController::class, 'index'])->name('admin.skus.index');
    Route::get('/skus/create', [AdminSkuController::class, 'create'])->name('admin.skus.create');
    Route::post('/skus', [AdminSkuController::class, 'store'])->name('admin.skus.store');
    Route::get('/skus/{id}/edit', [AdminSkuController::class, 'edit'])->name('admin.skus.edit');
    Route::put('/skus/{id}', [AdminSkuController::class, 'update'])->name('admin.skus.update');

    Route::get('/price-books', [AdminPriceBookController::class, 'index'])->name('admin.price-books.index');
    Route::get('/price-books/create', [AdminPriceBookController::class, 'create'])->name('admin.price-books.create');
    Route::post('/price-books', [AdminPriceBookController::class, 'store'])->name('admin.price-books.store');
    Route::get('/price-books/{id}/edit', [AdminPriceBookController::class, 'edit'])->name('admin.price-books.edit');
    Route::put('/price-books/{id}', [AdminPriceBookController::class, 'update'])->name('admin.price-books.update');

    Route::post('/price-books/{id}/items', [AdminPriceBookItemController::class, 'store'])->name('admin.price-books.items.store');
    Route::get('/price-books/{id}/items/{item}/edit', [AdminPriceBookItemController::class, 'edit'])->name('admin.price-books.items.edit');
    Route::put('/price-books/{id}/items/{item}', [AdminPriceBookItemController::class, 'update'])->name('admin.price-books.items.update');
    Route::delete('/price-books/{id}/items/{item}', [AdminPriceBookItemController::class, 'destroy'])->name('admin.price-books.items.destroy');

    Route::get('/templates', [AdminTemplateController::class, 'index'])->name('admin.templates.index');
    Route::get('/templates/create', [AdminTemplateController::class, 'create'])->name('admin.templates.create');
    Route::post('/templates', [AdminTemplateController::class, 'store'])->name('admin.templates.store');
    Route::get('/templates/{id}/edit', [AdminTemplateController::class, 'edit'])->name('admin.templates.edit');
    Route::put('/templates/{id}', [AdminTemplateController::class, 'update'])->name('admin.templates.update');

    Route::post('/templates/{id}/versions', [AdminTemplateVersionController::class, 'store'])->name('admin.templates.versions.store');
    Route::get('/templates/{id}/versions/{version}/edit', [AdminTemplateVersionController::class, 'edit'])->name('admin.templates.versions.edit');
    Route::put('/templates/{id}/versions/{version}', [AdminTemplateVersionController::class, 'update'])->name('admin.templates.versions.update');

    Route::get('/change-requests', [AdminChangeRequestController::class, 'index'])->name('admin.change-requests.index');
    Route::get('/change-requests/{id}', [AdminChangeRequestController::class, 'show'])->name('admin.change-requests.show');
    Route::get('/change-requests/{id}/snapshot.pdf', [AdminChangeRequestController::class, 'downloadSnapshotPdf'])->name('admin.change-requests.snapshot.pdf');
    Route::post('/change-requests/{id}/approve', [AdminChangeRequestController::class, 'approve'])->name('admin.change-requests.approve');
    Route::post('/change-requests/{id}/reject', [AdminChangeRequestController::class, 'reject'])->name('admin.change-requests.reject');

    Route::get('/audit-logs', [AdminAuditLogController::class, 'index'])->name('admin.audit-logs.index');
});

Route::middleware(['auth', 'role:admin,sales'])->prefix('ops')->group(function () {
    Route::get('/configurator-sessions', [ConfiguratorSessionController::class, 'index'])->name('ops.sessions.index');
    Route::get('/configurator-sessions/{id}', [ConfiguratorSessionController::class, 'show'])->name('ops.sessions.show');
    Route::get('/configurator-sessions/{id}/snapshot.pdf', [ConfiguratorSessionController::class, 'downloadSnapshotPdf'])->name('ops.sessions.snapshot.pdf');

    Route::get('/quotes', [QuoteController::class, 'index'])->name('ops.quotes.index');
    Route::get('/quotes/{id}', [QuoteController::class, 'show'])->name('ops.quotes.show');
    Route::get('/quotes/{id}/edit', [QuoteController::class, 'edit'])->name('ops.quotes.edit');
    Route::get('/quotes/{id}/snapshot.pdf', [QuoteController::class, 'downloadSnapshotPdf'])->name('ops.quotes.snapshot.pdf');
});
