<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Enums\ModerationRuleLanguage;
use App\Enums\ModerationRuleType;
use App\Http\Controllers\Controller;
use App\Models\ModerationRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModerationRuleController extends Controller
{
    public function index(Request $request): View
    {
        $type = $request->string('type')->toString();
        $language = $request->string('language')->toString();
        $search = $request->string('q')->toString();

        $rules = ModerationRule::query()
            ->when(
                in_array($type, array_column(ModerationRuleType::cases(), 'value'), true),
                fn ($query) => $query->where('type', $type),
            )
            ->when(
                in_array($language, array_column(ModerationRuleLanguage::cases(), 'value'), true),
                fn ($query) => $query->where('language', $language),
            )
            ->when(
                $search !== '',
                fn ($query) => $query->where('value', 'like', "%{$search}%"),
            )
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('manage.moderation-rules.index', [
            'rules' => $rules,
            'type' => $type,
            'language' => $language,
            'search' => $search,
            'types' => ModerationRuleType::cases(),
            'languages' => ModerationRuleLanguage::cases(),
        ]);
    }

    public function create(): View
    {
        return view('manage.moderation-rules.form', [
            'rule' => new ModerationRule(['is_active' => true, 'language' => ModerationRuleLanguage::ANY]),
            'types' => ModerationRuleType::cases(),
            'languages' => ModerationRuleLanguage::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        ModerationRule::query()->create($data);

        return redirect()
            ->route('manage.moderation-rules.index')
            ->with('status', 'تمت إضافة القاعدة.');
    }

    public function edit(ModerationRule $rule): View
    {
        return view('manage.moderation-rules.form', [
            'rule' => $rule,
            'types' => ModerationRuleType::cases(),
            'languages' => ModerationRuleLanguage::cases(),
        ]);
    }

    public function update(Request $request, ModerationRule $rule): RedirectResponse
    {
        $rule->update($this->validated($request));

        return redirect()
            ->route('manage.moderation-rules.index')
            ->with('status', 'تم تحديث القاعدة.');
    }

    public function destroy(ModerationRule $rule): RedirectResponse
    {
        $rule->delete();

        return redirect()
            ->route('manage.moderation-rules.index')
            ->with('status', 'تم حذف القاعدة.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['string'],
        ]);

        $count = ModerationRule::whereIn('id', $data['ids'])->delete();

        return back()->with('status', "تم حذف {$count} عنصراً.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:' . implode(',', array_column(ModerationRuleType::cases(), 'value'))],
            'value' => ['required', 'string', 'max:255'],
            'language' => ['required', 'string', 'in:' . implode(',', array_column(ModerationRuleLanguage::cases(), 'value'))],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
