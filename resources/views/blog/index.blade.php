@extends('layouts.landing')

@section('content')
@include('landing.nav')

<section class="lp-blog-page">
    <div class="lp-container">
        <a href="{{ route('public.landing') }}" class="lp-blog-back">
            <i class="fas fa-arrow-left"></i>
            Quay lại trang chủ
        </a>

        <div class="lp-blog-header">
            <h1 class="lp-section-title">
                Blog <span class="lp-text-gradient-primary">CS2</span>
            </h1>
            <p class="lp-muted">Kiến thức, mẹo giao dịch và hướng dẫn về thị trường skin CS2.</p>
        </div>

        <div class="lp-blog-grid">
            @foreach($posts as $post)
                <article class="lp-blog-card lp-glass">
                    <a href="{{ route('blog.show', $post['id']) }}" class="lp-blog-card-link">
                        <div class="lp-blog-card-cover @if(!empty($post['cover_url'])) lp-blog-card-cover--image @endif"
                            @if(!empty($post['cover_url'])) style="background-image: url('{{ $post['cover_url'] }}')" @endif>
                            @if(empty($post['cover_url']))
                                <span class="lp-blog-card-letter">{{ mb_substr($post['title'], 0, 1) }}</span>
                            @endif
                        </div>
                        <div class="lp-blog-card-body">
                            <div class="lp-blog-meta">
                                <span><i class="far fa-calendar"></i> {{ \Carbon\Carbon::parse($post['date'])->format('d/m/Y') }}</span>
                                <span><i class="far fa-clock"></i> {{ $post['read_time'] }}</span>
                            </div>
                            <h2 class="lp-blog-card-title">{{ $post['title'] }}</h2>
                            <p class="lp-blog-card-excerpt">{{ $post['excerpt'] }}</p>
                            <div class="lp-blog-tags">
                                @foreach($post['tags'] as $tag)
                                    <span class="lp-blog-tag"><i class="fas fa-tag"></i> {{ $tag }}</span>
                                @endforeach
                            </div>
                        </div>
                    </a>
                </article>
            @endforeach
        </div>
    </div>
</section>

@include('landing.footer')
@endsection
