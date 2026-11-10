<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Modül açma / kapatma kuralı ihlali (ör. başka bir modülün ihtiyaç duyduğu
 * modülü kapatmak). Genel 422 yerine uyarı bildirimi olarak döner.
 */
class ModuleGuardException extends RuntimeException
{
    public function render(Request $request): RedirectResponse
    {
        return back()->with('toast', [
            'type' => 'warning',
            'message' => $this->getMessage(),
        ]);
    }
}
