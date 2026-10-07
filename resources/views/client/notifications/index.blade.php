@extends('layouts.client')
@section('title','Notifications')
@section('content')
@push('head')@vite('resources/css/pages/client/notifications.css')@endpush
<div class="client-notifications-page">
<header class="client-module__header"><div><p class="client-module__eyebrow">CENTRE DE NOTIFICATIONS</p><h1>Notifications</h1><p>Retrouvez les événements importants liés à votre activité sur PROXIWORK.</p></div>@if($notifications->contains(fn($n)=>$n->read_at === null))<form method="POST" action="{{ route('client.notifications.read-all') }}">@csrf<button class="btn btn-secondary" type="submit">Tout marquer comme lu</button></form>@endif</header>
@if(session('success'))<div class="client-panel notification-alert" role="status">{{ session('success') }}</div>@endif
@if($notifications->isEmpty())<section class="client-panel notification-empty"><i class="fa-regular fa-bell" aria-hidden="true"></i><h2>Aucune notification</h2><p>Vous êtes à jour.</p></section>
@else
<section class="notification-list" aria-label="Liste des notifications">
@foreach($notifications as $notification)
<article class="client-panel notification-item {{ $notification->read_at ? 'is-read' : 'is-unread' }}">
<div class="notification-icon" aria-hidden="true"><i class="fa-solid {{ match($notification->data['action'] ?? '') { 'message'=>'fa-envelope', 'payment'=>'fa-credit-card', 'order'=>'fa-box', 'quotation'=>'fa-file-invoice-dollar', 'service_request'=>'fa-list-check', 'review'=>'fa-star', default=>'fa-bell' } }}"></i></div>
<div class="notification-body"><div class="notification-heading"><h2>{{ $notification->data['title'] ?? 'Notification' }}</h2>@unless($notification->read_at)<span class="unread-dot" aria-label="Non lue"></span>@endunless</div><p>{{ $notification->data['message'] ?? '' }}</p><time datetime="{{ optional($notification->created_at)->toISOString() }}">{{ optional($notification->created_at)->diffForHumans() }}</time>
@if(!$notification->read_at)<form method="POST" action="{{ route('client.notifications.read',$notification->id) }}"><@csrf><button class="link-button" type="submit">Marquer comme lue</button></form>@endif</div>
</article>
@endforeach
</section>
{{ $notifications->links() }}
@endif
</div>
@endsection