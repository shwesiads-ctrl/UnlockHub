<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\View\View;

class EmailPreferenceController extends Controller
{
    public function show(User $user): View
    {
        return view('mail.unsubscribe', [
            'email' => $user->email,
            'alreadyUnsubscribed' => ! $user->notify_email,
        ]);
    }

    public function unsubscribe(User $user): View
    {
        if ($user->notify_email) {
            $user->forceFill(['notify_email' => false])->save();
        }

        return view('mail.unsubscribed', ['email' => $user->email]);
    }
}
