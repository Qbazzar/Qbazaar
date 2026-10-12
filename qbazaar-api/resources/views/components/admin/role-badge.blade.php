@props(['role'])

@php
    $name = is_string($role) ? $role : $role->name;
    $key = 'admin.role_names.' . $name;
@endphp

<x-admin.badge tone="brand">{{ \Illuminate\Support\Facades\Lang::has($key) ? __($key) : $name }}</x-admin.badge>
