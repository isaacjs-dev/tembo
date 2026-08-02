<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PrintPreferencesController extends Controller
{
    public function edit()
    {
        $user = auth()->user();

        $systemDefaults = [
            'group_disciplines' => true,
            'shuffle_disciplines' => false,
            'show_discipline_name' => true,
            'hide_question_term' => false,
            'show_question_value' => true,
            'show_option_brackets' => false,
            'question_separator' => '.',
        ];

        $orgSettings = $user->organization ? ($user->organization->settings['print'] ?? []) : [];
        $userSettings = $user->settings['print'] ?? [];

        // Effective Visual Resolution
        $effectiveSettings = array_merge($systemDefaults, $orgSettings, $userSettings);

        return view('settings.print-preferences', compact('effectiveSettings'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'group_disciplines' => 'nullable|boolean',
            'shuffle_disciplines' => 'nullable|boolean',
            'show_discipline_name' => 'nullable|boolean',
            'hide_question_term' => 'nullable|boolean',
            'show_question_value' => 'nullable|boolean',
            'show_option_brackets' => 'nullable|boolean',
            'question_separator' => 'required|string|max:10',
            'custom_separator' => 'nullable|string|max:3',
        ]);

        $finalSeparator = $validated['question_separator'];
        if ($finalSeparator === 'custom') {
            $finalSeparator = $validated['custom_separator'] ?? '.';
        }

        $user = auth()->user();
        $settings = $user->settings ?? [];

        $settings['print'] = [
            'group_disciplines' => $request->boolean('group_disciplines'),
            'shuffle_disciplines' => $request->boolean('shuffle_disciplines'),
            'show_discipline_name' => $request->boolean('show_discipline_name'),
            'hide_question_term' => $request->boolean('hide_question_term'),
            'show_question_value' => $request->boolean('show_question_value'),
            'show_option_brackets' => $request->boolean('show_option_brackets'),
            'question_separator' => $finalSeparator,
        ];

        $user->settings = $settings;
        $user->save();

        return back()->with('status', 'Padrões de impressão salvos com sucesso!');
    }
}
