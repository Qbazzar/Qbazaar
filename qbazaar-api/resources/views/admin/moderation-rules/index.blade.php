@extends('admin.layout')

@section('title', 'قواعد الإشراف')
@section('heading', 'قواعد الإشراف')

@section('content')
    <x-admin.page-toolbar>
        <x-admin.filter-bar
            :reset-url="route('admin.moderation-rules.index')"
            :search="$search"
            placeholder="بحث بالقيمة…"
            :filters="[
                ['name' => 'type', 'label' => 'النوع', 'placeholder' => 'كل الأنواع', 'value' => $type,
                    'options' => collect($types)->mapWithKeys(fn ($case) => [$case->value => $case->label()['ar']])],
                ['name' => 'language', 'label' => 'اللغة', 'placeholder' => 'كل اللغات', 'value' => $language,
                    'options' => collect($languages)->mapWithKeys(fn ($case) => [$case->value => $case->label()['ar']])],
            ]"
        />
        <x-slot:actions>
            <x-admin.button :href="route('admin.moderation-rules.create')" icon="plus">إضافة قاعدة</x-admin.button>
        </x-slot:actions>
    </x-admin.page-toolbar>

    <x-admin.bulk-bar :action="route('admin.moderation-rules.bulk-destroy')" label="حذف المحدد" confirm="حذف العناصر المحددة نهائياً؟" />

    <x-admin.table
        caption="قواعد الإشراف"
        selectable
        :columns="['النوع', 'القيمة', 'اللغة', 'مفعّلة', ['label' => 'آخر تحديث', 'secondary' => true], ['label' => 'إجراءات', 'srOnly' => true]]"
        :empty="$rules->isEmpty()"
        empty-icon="shield"
        empty-message="لا توجد قواعد مطابقة."
    >
        @foreach ($rules as $rule)
            <x-admin.table.row>
                <x-admin.table.select :value="$rule->id" :label="'تحديد: ' . $rule->value" />
                <x-admin.table.cell label="النوع"><x-admin.badge :status="$rule->type" /></x-admin.table.cell>
                <x-admin.table.cell label="القيمة" class="font-mono text-ink-900">{{ $rule->value }}</x-admin.table.cell>
                <x-admin.table.cell label="اللغة" class="text-ink-700">{{ $rule->language->label()['ar'] }}</x-admin.table.cell>
                <x-admin.table.cell label="مفعّلة">
                    <x-admin.badge :tone="$rule->is_active ? 'success' : 'neutral'">{{ $rule->is_active ? 'نعم' : 'لا' }}</x-admin.badge>
                </x-admin.table.cell>
                <x-admin.table.cell label="آخر تحديث" secondary class="text-ink-500">{{ optional($rule->updated_at)->format('Y-m-d') }}</x-admin.table.cell>
                <x-admin.table.actions>
                    <x-admin.icon-button :href="route('admin.moderation-rules.edit', $rule)" icon="pencil" label="تعديل" />
                    <form method="POST" action="{{ route('admin.moderation-rules.destroy', $rule) }}" data-confirm="حذف هذه القاعدة؟">
                        @csrf
                        @method('DELETE')
                        <x-admin.icon-button icon="trash" label="حذف" variant="danger" />
                    </form>
                </x-admin.table.actions>
            </x-admin.table.row>
        @endforeach
    </x-admin.table>

    <x-admin.pagination :paginator="$rules" />
@endsection
