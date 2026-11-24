<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Repositories\Languages\LanguageInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

class LanguageController extends Controller
{
    private LanguageInterface $language;

    public function __construct(LanguageInterface $language)
    {
        $this->language = $language;
    }

    public function set_language(Request $request, $lang = null)
    {
         
        Session::put('locale', $lang);
        Session::save();
        $language = $this->language->builder()->where('code', $lang)->first();
        
        Session::put('language', $language);
        app()->setLocale(Session::get('locale'));


        Session::put('landing_locale', $lang);
        Session::save();
        Session::put('language', $language);
        app()->setLocale(Session::get('landing_locale'));

        Cache::flush();
        if (Auth::user()) {
            User::where('id', Auth::user()->id)->update(['language' => $language->code]);
        }
        return redirect()->back();
    }
}
