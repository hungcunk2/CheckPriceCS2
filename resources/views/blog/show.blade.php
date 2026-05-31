@extends('layouts.landing')

@section('content')
@include('landing.nav')

@php
    use App\Support\BlogContent;
    $formattedDate = \Carbon\Carbon::parse($post['date'])->format('d/m/Y');
@endphp

<article class="lp-blog-article">
    <div class="lp-container lp-blog-article-inner">
        <a href="{{ route('blog.index') }}" class="lp-blog-back">
            <i class="fas fa-arrow-left"></i>
            Quay lại blog
        </a>

        @if(!empty($post['cover_url']))
            <div class="lp-blog-hero">
                <img src="{{ $post['cover_url'] }}" alt="{{ $post['title'] }}" class="lp-blog-hero-img" width="1200" height="630">
            </div>
        @endif

        <div class="lp-blog-meta lp-blog-meta--large">
            <span><i class="far fa-calendar"></i> {{ $formattedDate }}</span>
            <span><i class="far fa-clock"></i> {{ $post['read_time'] }}</span>
        </div>

        <h1 class="lp-blog-article-title">{{ $post['title'] }}</h1>

        <div class="lp-blog-tags">
            @foreach($post['tags'] as $tag)
                <span class="lp-blog-tag lp-blog-tag--large"><i class="fas fa-tag"></i> {{ $tag }}</span>
            @endforeach
        </div>

        <div class="lp-blog-content">
            {!! BlogContent::toHtml($post['content']) !!}
        </div>

        @if($related !== [])
            <div class="lp-blog-related">
                <h3 class="lp-blog-related-title">Bài viết liên quan</h3>
                <div class="lp-blog-related-grid">
                    @foreach($related as $item)
                        <a href="{{ route('blog.show', $item['id']) }}" class="lp-blog-related-card lp-glass">
                            @if(!empty($item['cover_url']))
                                <div class="lp-blog-related-cover" style="background-image: url('{{ $item['cover_url'] }}')"></div>
                            @endif
                            <div class="lp-blog-related-card-body">
                            <div class="lp-muted" style="font-size:0.75rem;margin-bottom:0.25rem">
                                {{ \Carbon\Carbon::parse($item['date'])->format('d/m/Y') }}
                            </div>
                            <div class="lp-blog-related-card-title">{{ $item['title'] }}</div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</article>

@include('landing.footer')
@endsection
