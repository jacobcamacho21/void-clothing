<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Mail\CustomerSupportMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function show(Request $request): View
    {
        return view('shop.contact', [
            'email' => $request->user('customer')?->email ?? old('email'),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        Mail::to(config('mail.support.address'))
            ->send(new CustomerSupportMessage($data['email'], $data['message']));

        return redirect()
            ->route('shop.contact')
            ->with('status', 'Your message has been sent to VOID support. We will get back to you soon.');
    }
}
