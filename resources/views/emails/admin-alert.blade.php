<x-mail::message>
@foreach ($lines as $line)
{{ $line }}

@endforeach
@if ($url)
<x-mail::button :url="$url">
{{ __('admin_alerts.open') }}
</x-mail::button>
@endif

{{ __('admin_alerts.footer') }}
</x-mail::message>
