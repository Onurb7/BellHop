<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DemoLoginController extends Controller
{
    /**
     * One-click login for the portfolio "log in as..." switcher. Only
     * pulls from config('demo.accounts'), which does not contain a
     * super-admin key — that role is structurally unreachable here.
     */
    public function store(Request $request, string $role): RedirectResponse
    {
        if (! config('demo.login_enabled')) {
            throw new NotFoundHttpException;
        }

        $account = config("demo.accounts.{$role}");

        if (! $account || ! $account['email']) {
            throw new NotFoundHttpException;
        }

        $user = User::where('email', $account['email'])->firstOrFail();

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
