<x-mail::message>
# {{ __('messages.password_reset.subject') }}

{{ count($links) > 1 ? __('messages.password_reset.multiple_intro') : __('messages.password_reset.intro') }}

@foreach ($links as $link)
**{{ __('messages.password_reset.account_line', ['username' => $link['user']->username]) }}**

<x-mail::button :url="$link['url']">
{{ __('messages.password_reset.button') }}
</x-mail::button>

@endforeach
{{ __('messages.password_reset.ignore') }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
