@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4><i class="bi bi-list-ul me-2"></i>Öğrenilen Kelimeler</h4>
                    <a href="{{ route('manage.index') }}" class="btn btn-sm btn-light"><i class="bi bi-arrow-left me-1"></i> Yönetim Paneline Dön</a>
                </div>
                <div class="card-body">
                    @if(isset($error))
                        <div class="alert alert-danger">
                            {{ $error }}
                        </div>
                    @else
                        <!-- Filtreleme ve Arama -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header bg-info text-white">
                                        <i class="bi bi-funnel me-1"></i> Filtreleme ve Arama
                                    </div>
                                    <div class="card-body">
                                        <form method="GET" action="{{ route('manage.words') }}" class="row g-3">
                                            <div class="col-md-4">
                                                <label for="search" class="form-label">Kelime Ara</label>
                                                <input type="text" class="form-control" id="search" name="search" value="{{ $search ?? '' }}" placeholder="Kelime veya tanım ara...">
                                            </div>
                                            <div class="col-md-3">
                                                <label for="category" class="form-label">Kategori</label>
                                                <select class="form-select" id="category" name="category">
                                                    <option value="">Tüm Kategoriler</option>
                                                    @foreach($categories as $cat)
                                                        <option value="{{ $cat }}" {{ ($category ?? '') == $cat ? 'selected' : '' }}>{{ $cat }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label for="sort" class="form-label">Sıralama</label>
                                                <select class="form-select" id="sort" name="sort">
                                                    <option value="word" {{ ($sort ?? 'word') == 'word' ? 'selected' : '' }}>Kelime</option>
                                                    <option value="category" {{ ($sort ?? '') == 'category' ? 'selected' : '' }}>Kategori</option>
                                                    <option value="frequency" {{ ($sort ?? '') == 'frequency' ? 'selected' : '' }}>Kullanım Sıklığı</option>
                                                    <option value="created_at" {{ ($sort ?? '') == 'created_at' ? 'selected' : '' }}>Öğrenme Tarihi</option>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label for="order" class="form-label">Sıra</label>
                                                <select class="form-select" id="order" name="order">
                                                    <option value="asc" {{ ($order ?? 'asc') == 'asc' ? 'selected' : '' }}>Artan</option>
                                                    <option value="desc" {{ ($order ?? '') == 'desc' ? 'selected' : '' }}>Azalan</option>
                                                </select>
                                            </div>
                                            <div class="col-md-1 d-flex align-items-end">
                                                <button type="submit" class="btn btn-primary w-100">
                                                    <i class="bi bi-search"></i>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Sonuç Sayısı -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <span class="badge bg-primary">Toplam: {{ $words->total() }} kelime</span>
                                @if(!empty($search))
                                    <span class="badge bg-info">Arama: "{{ $search }}"</span>
                                @endif
                                @if(!empty($category))
                                    <span class="badge bg-success">Kategori: {{ $category }}</span>
                                @endif
                            </div>
                            <div>
                                <span class="text-muted">Sayfa {{ $words->currentPage() }}/{{ $words->lastPage() }}</span>
                            </div>
                        </div>
                        
                        <!-- Kelime Tablosu -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>#</th>
                                        <th>Kelime</th>
                                        <th>Kategori</th>
                                        <th>Tanım</th>
                                        <th>Sıklık</th>
                                        <th>Güven</th>
                                        <th>Öğrenme Tarihi</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($words as $index => $word)
                                        <tr>
                                            <td>{{ $words->firstItem() + $index }}</td>
                                            <td><strong>{{ $word->word }}</strong></td>
                                            <td>{{ $word->category }}</td>
                                            <td>
                                                @if(!empty($word->sentence))
                                                    {{ \Illuminate\Support\Str::limit($word->sentence, 50) }}
                                                @else
                                                    <span class="text-muted">Tanım yok</span>
                                                @endif
                                            </td>
                                            <td>{{ $word->frequency }}</td>
                                            <td>{{ number_format($word->confidence * 100, 1) }}%</td>
                                            <td>{{ $word->created_at->format('d.m.Y H:i') }}</td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary view-word-details" data-word="{{ $word->word }}">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center">Herhangi bir kelime bulunamadı</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Sayfalama -->
                        <div class="pagination-container mt-4">
                            <nav aria-label="Kelime listesi sayfalama">
                                <ul class="pagination pagination-lg justify-content-center">
                                    <!-- İlk sayfa -->
                                    <li class="page-item {{ $words->onFirstPage() ? 'disabled' : '' }}">
                                        <a class="page-link" href="{{ $words->url(1) }}" aria-label="İlk">
                                            <i class="bi bi-chevron-double-left"></i>
                                        </a>
                                    </li>
                                    
                                    <!-- Önceki sayfa -->
                                    <li class="page-item {{ $words->onFirstPage() ? 'disabled' : '' }}">
                                        <a class="page-link" href="{{ $words->previousPageUrl() }}" aria-label="Önceki">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                    
                                    <!-- Mevcut sayfa bilgisi -->
                                    <li class="page-item active">
                                        <span class="page-link">
                                            {{ $words->currentPage() }} / {{ $words->lastPage() }}
                                        </span>
                                    </li>
                                    
                                    <!-- Sonraki sayfa -->
                                    <li class="page-item {{ !$words->hasMorePages() ? 'disabled' : '' }}">
                                        <a class="page-link" href="{{ $words->nextPageUrl() }}" aria-label="Sonraki">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                    
                                    <!-- Son sayfa -->
                                    <li class="page-item {{ !$words->hasMorePages() ? 'disabled' : '' }}">
                                        <a class="page-link" href="{{ $words->url($words->lastPage()) }}" aria-label="Son">
                                            <i class="bi bi-chevron-double-right"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                            
                            <!-- Sayfa seçici (Bootstrap dropdown) -->
                            <div class="d-flex justify-content-center mt-2">
                                <div class="dropdown">
                                    <button class="btn btn-outline-primary dropdown-toggle" type="button" id="pageDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                        Sayfa: {{ $words->currentPage() }}
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="pageDropdown" style="max-height: 200px; overflow-y: auto;">
                                        @for ($i = 1; $i <= $words->lastPage(); $i++)
                                            <li>
                                                <a class="dropdown-item {{ $i == $words->currentPage() ? 'active' : '' }}" 
                                                   href="{{ $words->url($i) }}">
                                                    {{ $i }}
                                                </a>
                                            </li>
                                        @endfor
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Modern Sayfalama -->
                        <div class="mt-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <!-- Önceki / Sonraki butonları -->
                                <div>
                                    @if (!$words->onFirstPage())
                                        <a href="{{ $words->url(1) }}" class="btn btn-sm btn-outline-secondary me-1">
                                            <i class="bi bi-chevron-bar-left"></i>
                                        </a>
                                        <a href="{{ $words->previousPageUrl() }}" class="btn btn-sm btn-primary">
                                            <i class="bi bi-arrow-left me-1"></i> Önceki
                                        </a>
                                    @else
                                        <button class="btn btn-sm btn-outline-secondary me-1" disabled>
                                            <i class="bi bi-chevron-bar-left"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-primary" disabled>
                                            <i class="bi bi-arrow-left me-1"></i> Önceki
                                        </button>
                                    @endif
                                </div>
                                
                                <!-- Sayfa göstergesi - Sayfa seçici -->
                                <div class="d-flex align-items-center">
                                    <span class="mx-3 fs-5">
                                        <select class="form-select form-select-sm d-inline-block" style="width: auto" 
                                                onchange="window.location.href = this.value">
                                            @for ($i = 1; $i <= $words->lastPage(); $i++)
                                                <option value="{{ $words->url($i) }}" {{ $i == $words->currentPage() ? 'selected' : '' }}>
                                                    {{ $i }}
                                                </option>
                                            @endfor
                                        </select>
                                        / {{ $words->lastPage() }}
                                    </span>
                                </div>
                                
                                <!-- Sonraki / Son butonları -->
                                <div>
                                    @if ($words->hasMorePages())
                                        <a href="{{ $words->nextPageUrl() }}" class="btn btn-sm btn-primary">
                                            Sonraki <i class="bi bi-arrow-right ms-1"></i>
                                        </a>
                                        <a href="{{ $words->url($words->lastPage()) }}" class="btn btn-sm btn-outline-secondary ms-1">
                                            <i class="bi bi-chevron-bar-right"></i>
                                        </a>
                                    @else
                                        <button class="btn btn-sm btn-outline-primary" disabled>
                                            Sonraki <i class="bi bi-arrow-right ms-1"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary ms-1" disabled>
                                            <i class="bi bi-chevron-bar-right"></i>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Kelime Detayları Modal -->
<div class="modal fade" id="wordDetailsModal" tabindex="-1" aria-labelledby="wordDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="wordDetailsModalLabel">Kelime Detayları</h5>
                <button type="button" class="btn-close bg-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <div id="wordDetailsContent">
                    <div class="d-flex justify-content-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Yükleniyor...</span>
                        </div>
                    </div>
                    <p class="text-center mt-2">Kelime bilgileri yükleniyor...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Kelime detaylarını göster
    $('.view-word-details').on('click', function() {
        const word = $(this).data('word');
        $('#wordDetailsModalLabel').text('Kelime Detayları: ' + word);
        $('#wordDetailsModal').modal('show');
        
        // Kelime bilgilerini yükle
        $.ajax({
            url: '/api/ai/word/' + encodeURIComponent(word),
            method: 'GET',
            timeout: 10000, // 10 saniye timeout
            success: function(response) {
                if (response.success) {
                    let content = '<div class="row">';
                    
                    // Ana bilgiler
                    content += '<div class="col-md-6"><h5>Genel Bilgiler</h5>';
                    content += '<ul class="list-group mb-3">';
                    content += '<li class="list-group-item"><strong>Kelime:</strong> ' + response.data.word + '</li>';
                    
                    if (response.data.category) {
                        content += '<li class="list-group-item"><strong>Kategori:</strong> ' + response.data.category + '</li>';
                    }
                    
                    if (response.data.frequency) {
                        content += '<li class="list-group-item"><strong>Sıklık:</strong> ' + response.data.frequency + '</li>';
                    }
                    
                    if (response.data.confidence) {
                        content += '<li class="list-group-item"><strong>Güven:</strong> ' + (response.data.confidence * 100).toFixed(1) + '%</li>';
                    }
                    
                    if (response.data.created_at) {
                        content += '<li class="list-group-item"><strong>Öğrenme Tarihi:</strong> ' + response.data.created_at + '</li>';
                    }
                    
                    content += '</ul></div>';
                    
                    // Tanımlar ve örnekler
                    content += '<div class="col-md-6"><h5>Tanım ve Örnekler</h5>';
                    if (response.data.definitions && response.data.definitions.length > 0) {
                        content += '<div class="mb-3"><strong>Tanımlar:</strong>';
                        content += '<ul class="list-group">';
                        response.data.definitions.forEach(function(definition) {
                            content += '<li class="list-group-item">' + definition + '</li>';
                        });
                        content += '</ul></div>';
                    } else if (response.data.definition) {
                        content += '<div class="mb-3"><strong>Tanım:</strong>';
                        content += '<ul class="list-group">';
                        content += '<li class="list-group-item">' + response.data.definition + '</li>';
                        content += '</ul></div>';
                    } else {
                        content += '<p class="text-muted">Tanım bilgisi bulunamadı.</p>';
                    }
                    
                    if (response.data.examples && response.data.examples.length > 0) {
                        content += '<div class="mb-3"><strong>Örnekler:</strong>';
                        content += '<ul class="list-group">';
                        response.data.examples.forEach(function(example) {
                            content += '<li class="list-group-item">' + example + '</li>';
                        });
                        content += '</ul></div>';
                    }
                    content += '</div>';
                    
                    // İlişkili kelimeler
                    content += '<div class="col-md-12 mt-3"><h5>İlişkili Kelimeler</h5>';
                    content += '<div class="row">';
                    
                    // Eş anlamlılar
                    content += '<div class="col-md-4"><strong>Eş Anlamlılar:</strong>';
                    if (response.data.synonyms && response.data.synonyms.length > 0) {
                        content += '<ul class="list-group">';
                        response.data.synonyms.forEach(function(synonym) {
                            content += '<li class="list-group-item">' + synonym + '</li>';
                        });
                        content += '</ul>';
                    } else {
                        content += '<p class="text-muted">Eş anlamlı kelime bulunamadı.</p>';
                    }
                    content += '</div>';
                    
                    // Zıt anlamlılar
                    content += '<div class="col-md-4"><strong>Zıt Anlamlılar:</strong>';
                    if (response.data.antonyms && response.data.antonyms.length > 0) {
                        content += '<ul class="list-group">';
                        response.data.antonyms.forEach(function(antonym) {
                            content += '<li class="list-group-item">' + antonym + '</li>';
                        });
                        content += '</ul>';
                    } else {
                        content += '<p class="text-muted">Zıt anlamlı kelime bulunamadı.</p>';
                    }
                    content += '</div>';
                    
                    // İlişkili kelimeler
                    content += '<div class="col-md-4"><strong>İlişkili Kelimeler:</strong>';
                    if (response.data.related && response.data.related.length > 0) {
                        content += '<ul class="list-group">';
                        response.data.related.forEach(function(related) {
                            content += '<li class="list-group-item">' + related + '</li>';
                        });
                        content += '</ul>';
                    } else {
                        content += '<p class="text-muted">İlişkili kelime bulunamadı.</p>';
                    }
                    content += '</div>';
                    
                    content += '</div></div>';
                    content += '</div>';
                    
                    $('#wordDetailsContent').html(content);
                } else {
                    let errorMsg = 'Kelime bilgileri yüklenirken bir hata oluştu.';
                    if (response.message) {
                        errorMsg += ' Hata: ' + response.message;
                    }
                    $('#wordDetailsContent').html('<div class="alert alert-danger">' + errorMsg + '</div>');
                }
            },
            error: function(xhr, status, error) {
                let errorMsg = 'Kelime bilgileri yüklenirken bir hata oluştu.';
                
                if (status === 'timeout') {
                    errorMsg = 'Sunucudan yanıt gelmedi, lütfen tekrar deneyin.';
                } else if (xhr.status === 404) {
                    errorMsg = 'Kelime bilgisi bulunamadı.';
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg += ' Hata: ' + xhr.responseJSON.message;
                } else if (error) {
                    errorMsg += ' Hata: ' + error;
                }
                
                $('#wordDetailsContent').html(
                    '<div class="alert alert-danger">' + errorMsg + '</div>' + 
                    '<div class="text-center mt-3">' +
                    '<button type="button" class="btn btn-outline-primary retry-word-details" data-word="' + word + '">' +
                    '<i class="bi bi-arrow-clockwise me-1"></i> Tekrar Dene</button>' +
                    '</div>'
                );
                
                // Tekrar deneme butonu için event handler
                $('.retry-word-details').on('click', function() {
                    const word = $(this).data('word');
                    $('#wordDetailsContent').html(
                        '<div class="d-flex justify-content-center">' +
                        '<div class="spinner-border text-primary" role="status">' +
                        '<span class="visually-hidden">Yükleniyor...</span>' +
                        '</div></div>' +
                        '<p class="text-center mt-2">Kelime bilgileri yükleniyor...</p>'
                    );
                    
                    // Kelime bilgilerini tekrar yükle
                    $('.view-word-details[data-word="' + word + '"]').trigger('click');
                });
            }
        });
    });
});
</script>
@endsection 