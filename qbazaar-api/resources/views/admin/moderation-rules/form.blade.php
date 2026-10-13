@extends('admin.layout')

@section('title', $rule->exists ? 'تعديل قاعدة' : 'إضافة قاعدة')
@section('heading', $rule->exists ? 'تعديل قاعدة إشراف' : 'إضافة قاعدة إشراف')

@section('content')
    @php
        $isEdit = $rule->exists;
        $currentType = old('type', $rule->type?->value);
        $currentLanguage = old('language', $rule->language?->value ?? 'any');
    @endphp

    {{-- group/rule: the side help shows only the selected type's note (CSS :has, no script). --}}
    <x-admin.page
        class="group/rule"
        :title="$isEdit ? 'تعديل قاعدة إشراف' : 'إضافة قاعدة إشراف'"
        :crumbs="[['قواعد الإشراف', route('admin.moderation-rules.index')], $isEdit ? 'تعديل' : 'قاعدة جديدة']"
        :form="$isEdit ? [route('admin.moderation-rules.update', $rule), 'PUT'] : [route('admin.moderation-rules.store')]"
        :cancel="route('admin.moderation-rules.index')"
        :submit="$isEdit ? 'حفظ التغييرات' : 'إضافة'"
    >
        @if ($isEdit)
            <x-slot:badges>
                <x-admin.badge :status="$rule->type" />
                <x-admin.badge :tone="$rule->is_active ? 'success' : 'neutral'">{{ $rule->is_active ? 'مفعّلة' : 'معطّلة' }}</x-admin.badge>
            </x-slot:badges>

            <x-slot:menu>
                <x-admin.menu.item danger icon="trash" :action="route('admin.moderation-rules.destroy', $rule)" method="DELETE" confirm="حذف هذه القاعدة؟">
                    حذف القاعدة
                </x-admin.menu.item>
            </x-slot:menu>
        @endif

        <x-admin.form-section title="تعريف القاعدة" description="يُفحص نص الإعلانات تلقائيًا بالقواعد المفعّلة." cols="2">
            <x-admin.select name="type" label="النوع" required>
                <option value="">اختر…</option>
                @foreach ($types as $case)
                    <option value="{{ $case->value }}" @selected($currentType === $case->value)>{{ $case->label()['ar'] }}</option>
                @endforeach
            </x-admin.select>

            <x-admin.select name="language" label="اللغة" required>
                @foreach ($languages as $case)
                    <option value="{{ $case->value }}" @selected($currentLanguage === $case->value)>{{ $case->label()['ar'] }}</option>
                @endforeach
            </x-admin.select>

            <x-admin.input name="value" label="القيمة" :value="old('value', $rule->value)" required maxlength="255" class="col-span-full" />
        </x-admin.form-section>

        <x-slot:side>
            <x-admin.side-card title="الحالة">
                <x-admin.checkbox name="is_active" label="مفعّلة" :checked="old('is_active', $rule->is_active ?? true)" unchecked-value="0" />
                <p class="mt-2 text-xs text-ink-500">القاعدة المعطّلة تبقى محفوظة ولا تُطبَّق على الإعلانات.</p>
            </x-admin.side-card>

            <x-admin.side-card title="دليل سريع">
                <dl class="grid gap-3 text-[13px] leading-relaxed">
                    <div class="group-has-[#type_option[value^=blocked]:checked]/rule:hidden">
                        <dt class="font-bold text-ink-900">{{ \App\Enums\ModerationRuleType::BANNED_WORD->label()['ar'] }}</dt>
                        <dd class="mt-0.5 text-ink-500">كلمة أو عبارة تُبحث داخل العنوان والوصف، بعد توحيد حالة الأحرف وحذف الرموز بين الحروف، فتُكتشف «<bdi dir="ltr">b.i.t.c.o.i.n</bdi>» أيضًا.</dd>
                    </div>
                    <div class="group-has-[#type_option[value^=banned]:checked]/rule:hidden">
                        <dt class="font-bold text-ink-900">{{ \App\Enums\ModerationRuleType::BLOCKED_DOMAIN->label()['ar'] }}</dt>
                        <dd class="mt-0.5 text-ink-500">اسم نطاق بلا http مثل <bdi dir="ltr">example.com</bdi>. أي رابط إليه في نص الإعلان يُعدّ مخالفة، حتى لو كان ضمن النطاقات المسموح بها.</dd>
                    </div>
                    <div class="border-t border-ink-200 pt-3">
                        <dt class="font-bold text-ink-900">اللغة</dt>
                        <dd class="mt-0.5 text-ink-500">«الكل» تُطبَّق دائمًا، و«العربية» أو «الإنجليزية» تُطبَّق فقط عندما يحتوي نص الإعلان على حروف تلك اللغة.</dd>
                    </div>
                </dl>
            </x-admin.side-card>

            @if ($isEdit)
                <x-admin.side-card title="بيانات القاعدة">
                    <x-admin.meta-list>
                        <x-admin.meta-item label="المعرّف الداخلي" ltr>{{ $rule->id }}</x-admin.meta-item>
                        <x-admin.meta-item label="تاريخ الإنشاء" ltr>{{ $rule->created_at?->format('Y-m-d H:i') }}</x-admin.meta-item>
                        <x-admin.meta-item label="آخر تعديل" ltr>{{ $rule->updated_at?->format('Y-m-d H:i') }}</x-admin.meta-item>
                    </x-admin.meta-list>
                </x-admin.side-card>
            @endif
        </x-slot:side>
    </x-admin.page>
@endsection
