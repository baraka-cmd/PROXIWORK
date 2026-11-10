@extends('layouts.public')
@section('title','Questions fréquentes — PROXIWORK')
@section('meta_description','Retrouvez les réponses aux questions fréquentes sur PROXIWORK.')
@section('content')
<main class="page-container" style="max-width:960px;padding-top:3rem;padding-bottom:4rem"><h1>Questions fréquentes</h1><p>Des réponses pour mieux utiliser PROXIWORK.</p>@foreach($categories as $category)<section class="admin-card" style="margin-top:1.5rem"><h2>{{ $category->name }}</h2>@foreach($category->items as $item)<details style="padding:1rem 0;border-bottom:1px solid #e5e7eb"><summary><strong>{{ $item->question }}</strong></summary><div class="public-rich-text" style="padding-top:.75rem">{!! nl2br(e($item->answer)) !!}</div></details>@endforeach</section>@endforeach</main>
@endsection