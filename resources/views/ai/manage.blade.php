@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4><i class="bi bi-gear-fill me-2"></i>SoneAI Yapay Zeka Yönetim Paneli</h4>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="learning-tab" data-bs-toggle="tab" data-bs-target="#learning" type="button" role="tab" aria-controls="learning" aria-selected="true">
                                <i class="bi bi-book me-1"></i> Öğrenme Sistemi
                    </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="stats-tab" data-bs-toggle="tab" data-bs-target="#stats" type="button" role="tab" aria-controls="stats" aria-selected="false">
                                <i class="bi bi-bar-chart me-1"></i> İstatistikler
                    </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="word-tab" data-bs-toggle="tab" data-bs-target="#word" type="button" role="tab" aria-controls="word" aria-selected="false">
                                <i class="bi bi-type me-1"></i> Kelime İşlemleri
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="maintenance-tab" data-bs-toggle="tab" data-bs-target="#maintenance" type="button" role="tab" aria-controls="maintenance" aria-selected="false">
                                <i class="bi bi-tools me-1"></i> Bakım
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" href="{{ route('manage.words') }}">
                                <i class="bi bi-list-ul me-1"></i> Kelime Listesi
                            </a>
                        </li>
                    </ul>
                    
                    <div class="tab-content mt-3" id="myTabContent">
                        <!-- Öğrenme Sistemi Tab -->
                        <div class="tab-pane fade show active" id="learning" role="tabpanel" aria-labelledby="learning-tab">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header bg-info text-white">
                                            <i class="bi bi-play-circle me-1"></i> Öğrenme İşlemi Başlat
                            </div>
                                        <div class="card-body">
                                            <form id="startLearningForm">
                                                <div class="mb-3">
                                                    <label for="wordLimit" class="form-label">Kelime Limiti</label>
                                                    <input type="number" class="form-control" id="wordLimit" name="word_limit" min="1" max="1000" value="50">
                                                    <div class="form-text">Öğrenilecek maksimum kelime sayısı</div>
                            </div>
                                                
                                                <div class="mb-3">
                                                    <label for="manualWords" class="form-label">Manuel Kelimeler (isteğe bağlı)</label>
                                                    <textarea class="form-control" id="manualWords" rows="3" placeholder="Her satıra bir kelime yazın"></textarea>
                                                    <div class="form-text">Özel olarak öğrenmesini istediğiniz kelimeleri girin</div>
                    </div>
                    
                                                <button type="button" id="startLearningBtn" class="btn btn-primary">
                                                    <i class="bi bi-play-fill me-1"></i> Öğrenmeyi Başlat
                                                </button>
                                            </form>
                            </div>
                        </div>
                    </div>
                    
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header bg-info text-white">
                                            <i class="bi bi-info-circle me-1"></i> Öğrenme Durumu
                            </div>
                                        <div class="card-body">
                                            <div id="learningStatus">
                                                <div class="d-flex justify-content-center">
                                                    <div class="spinner-border text-primary" role="status">
                                                        <span class="visually-hidden">Yükleniyor...</span>
                        </div>
                    </div>
                                                <p class="text-center mt-2">Durum bilgisi yükleniyor...</p>
                            </div>
                        </div>
                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- İstatistikler Tab -->
                        <div class="tab-pane fade" id="stats" role="tabpanel" aria-labelledby="stats-tab">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="card">
                                        <div class="card-header bg-info text-white">
                                            <i class="bi bi-graph-up me-1"></i> Sistem İstatistikleri
                            </div>
                                        <div class="card-body">
                                            <div id="statsContent">
                                                <div class="d-flex justify-content-center">
                                                    <div class="spinner-border text-primary" role="status">
                                                        <span class="visually-hidden">Yükleniyor...</span>
                            </div>
                            </div>
                                                <p class="text-center mt-2">İstatistikler yükleniyor...</p>
                            </div>
                        </div>
                            </div>
                        </div>
                            </div>
                        </div>
                        
                        <!-- Kelime İşlemleri Tab -->
                        <div class="tab-pane fade" id="word" role="tabpanel" aria-labelledby="word-tab">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header bg-info text-white">
                                            <i class="bi bi-plus-circle me-1"></i> Kelime Öğrenme
                        </div>
                                        <div class="card-body">
                                            <form id="learnWordForm">
                                                <div class="mb-3">
                                                    <label for="wordToLearn" class="form-label">Kelime</label>
                                                    <input type="text" class="form-control" id="wordToLearn" required>
                                                </div>
                                                <button type="submit" class="btn btn-primary">Kelimeyi Öğren</button>
                                            </form>
                                            <div id="learnWordResult" class="mt-3"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header bg-info text-white">
                                            <i class="bi bi-search me-1"></i> Kelime Ara
                        </div>
                                        <div class="card-body">
                                            <form id="searchWordForm">
                                                <div class="mb-3">
                                                    <label for="wordToSearch" class="form-label">Kelime</label>
                                                    <input type="text" class="form-control" id="wordToSearch" required>
                                                </div>
                                                <button type="submit" class="btn btn-primary">Kelime Ara</button>
                                            </form>
                                            <div id="searchWordResult" class="mt-3"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <div class="card">
                                        <div class="card-header">Akıllı Cümle Oluştur</div>
                                        <div class="card-body">
                                            <form id="generateSentencesForm">
                                                <div class="mb-3">
                                                    <label for="wordForSentences" class="form-label">Kelime</label>
                                                    <input type="text" class="form-control" id="wordForSentences" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="sentenceCount" class="form-label">Cümle Sayısı</label>
                                                    <select class="form-control" id="sentenceCount">
                                                        <option value="3">3</option>
                                                        <option value="5">5</option>
                                                        <option value="7">7</option>
                                                        <option value="10">10</option>
                                                    </select>
                                                </div>
                                                <div class="form-check mb-3">
                                                    <input class="form-check-input" type="checkbox" id="saveSentences" checked>
                                                    <label class="form-check-label" for="saveSentences">
                                                        Cümleleri Veritabanına Kaydet
                                                    </label>
                                                </div>
                                                <button type="submit" class="btn btn-primary">Cümle Oluştur</button>
                                            </form>
                                            <div id="generateSentencesResult" class="mt-3">
                                                <div class="sentences-list"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <div class="card">
                                        <div class="card-header bg-success text-white">
                                            <i class="bi bi-magic me-1"></i> Otomatik Kelime Seçimi ve Cümle Oluşturma
                                        </div>
                                        <div class="card-body">
                                            <div class="alert alert-info">
                                                <i class="bi bi-info-circle-fill me-2"></i> Bu özellik, sistem tarafından otomatik olarak kelimeler seçer ve bu kelimelerle akıllı cümleler oluşturur.
                                            </div>
                                            
                                            <form id="autoSentencesForm">
                                                <div class="mb-3">
                                                    <label for="autoWordCount" class="form-label">Kaç Kelime Seçilecek</label>
                                                    <select class="form-control" id="autoWordCount">
                                                        <option value="5">5 kelime</option>
                                                        <option value="10" selected>10 kelime</option>
                                                        <option value="20">20 kelime</option>
                                                        <option value="30">30 kelime</option>
                                                    </select>
                                                </div>
                                                <div class="form-check mb-3">
                                                    <input class="form-check-input" type="checkbox" id="autoSaveSentences" checked>
                                                    <label class="form-check-label" for="autoSaveSentences">
                                                        Cümleleri Veritabanına Kaydet
                                                    </label>
                                                </div>
                                                <button type="submit" class="btn btn-success">
                                                    <i class="bi bi-magic me-1"></i> Otomatik Cümle Oluştur
                                                </button>
                                            </form>
                                            
                                            <div id="autoSentencesResult" class="mt-3">
                                                <div class="auto-sentences-list"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Bakım Tab -->
                        <div class="tab-pane fade" id="maintenance" role="tabpanel" aria-labelledby="maintenance-tab">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="card">
                                        <div class="card-header bg-info text-white">
                                            <i class="bi bi-wrench me-1"></i> Bakım İşlemleri
                    </div>
                                        <div class="card-body">
                                            <div class="alert alert-warning">
                                                <i class="bi bi-exclamation-triangle-fill me-1"></i> <strong>Dikkat!</strong> Bu işlemler geri alınamaz!
                </div>
                
                                            <button type="button" id="clearLearningBtn" class="btn btn-danger">
                                                <i class="bi bi-trash me-1"></i> Öğrenme Sistemini Temizle
                                            </button>
                        </div>
                    </div>
                    </div>
                </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Sayfa yüklendiğinde aktif sekmeyi kontrol et
    if (window.location.hash) {
        let hash = window.location.hash;
        $('.nav-tabs a[href="' + hash + '"]').tab('show');
    }

    // Tab değişiminde URL hash'ini güncelle
    $('.nav-tabs a').on('shown.bs.tab', function (e) {
        window.location.hash = e.target.getAttribute('data-bs-target');
    });

    // Öğrenme durumunu 5 saniyede bir kontrol et
    function checkLearningStatus() {
        $.ajax({
            url: '/manage/learning/progress',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (!response.success) {
                    $('#learningStatus').html('<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Durum bilgisi alınamadı: ' + response.message + '</div>');
                    return;
                }
                
                let status = response.data;
                let html = '';

                if (status.is_learning) {
                    html += '<div class="alert alert-info"><i class="bi bi-info-circle-fill me-2"></i>Öğrenme işlemi aktif</div>';
                    html += '<div class="progress mb-3">';
                    let percent = status.progress_percent || 0;
                    html += '<div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: ' + percent + '%" aria-valuenow="' + percent + '" aria-valuemin="0" aria-valuemax="100">' + percent + '%</div>';
                    html += '</div>';
                } else {
                    html += '<div class="alert alert-secondary"><i class="bi bi-pause-circle-fill me-2"></i>Öğrenme işlemi beklemede</div>';
                }

                html += '<div class="table-responsive">';
                html += '<table class="table table-bordered">';
                html += '<tr><th>Toplam Kelime Limiti</th><td>' + (status.word_limit || status.total || 0) + '</td></tr>';
                html += '<tr><th>Öğrenilen Kelimeler</th><td>' + (status.learned_count || status.learned || 0) + '</td></tr>';
                html += '<tr><th>Hata Sayısı</th><td>' + (status.error_count || status.errors || 0) + '</td></tr>';
                html += '<tr><th>Son Öğrenilen Kelime</th><td>' + (status.last_word ? status.last_word : '-') + '</td></tr>';
                
                if (status.start_time) {
                    html += '<tr><th>Başlangıç Zamanı</th><td>' + status.start_time + '</td></tr>';
                }
                
                if (status.elapsed_time) {
                    let elapsed = status.elapsed_time;
                    let hours = Math.floor(elapsed / 3600);
                    let minutes = Math.floor((elapsed % 3600) / 60);
                    let seconds = elapsed % 60;
                    let timeStr = '';
                    
                    if (hours > 0) timeStr += hours + ' saat ';
                    if (minutes > 0) timeStr += minutes + ' dakika ';
                    timeStr += seconds + ' saniye';
                    
                    html += '<tr><th>İşlem Süresi</th><td>' + timeStr + '</td></tr>';
                } else if (status.elapsed_time_formatted) {
                    html += '<tr><th>Güncelleme</th><td>' + status.elapsed_time_formatted + '</td></tr>';
                }
                
                if (status.estimated_end) {
                    html += '<tr><th>Tahmini Bitiş</th><td>' + status.estimated_end + '</td></tr>';
                }
                
                if (status.word_count) {
                    html += '<tr><th>Veritabanındaki Toplam Kelime</th><td>' + status.word_count + '</td></tr>';
                }
                
                html += '</table>';
                html += '</div>';

                if (status.is_learning) {
                    html += '<button type="button" id="stopLearningBtn" class="btn btn-danger mt-2"><i class="bi bi-stop-fill me-1"></i>Öğrenmeyi Durdur</button>';
                } else {
                    // Başlatma butonu göster
                    html += '<button type="button" id="quickStartBtn" class="btn btn-primary mt-2"><i class="bi bi-lightning-fill me-1"></i>50 Kelime Öğrenmeyi Başlat</button>';
                }

                $('#learningStatus').html(html);

                // Öğrenmeyi durdurma işlemini bağla
                $('#stopLearningBtn').on('click', function() {
                    stopLearning();
                });
                
                // Hızlı başlatma butonu
                $('#quickStartBtn').on('click', function() {
                    quickStartLearning(50);
                });
            },
            error: function(xhr, status, error) {
                $('#learningStatus').html('<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Durum bilgisi alınamadı: ' + error + '</div>');
            }
        });
    }
    
    // Hızlı başlatma fonksiyonu
    function quickStartLearning(wordLimit) {
        $.ajax({
            url: '/manage/learning/start',
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: {
                word_limit: wordLimit
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('Öğrenme işlemi başlatıldı. ' + wordLimit + ' kelime öğrenilecek.');
                    checkLearningStatus();
                } else {
                    alert('Hata: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                alert('Bir hata oluştu: ' + error);
            }
        });
    }

    // Öğrenmeyi durdur
    function stopLearning() {
        $.ajax({
            url: '/api/manage/learning/stop',
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#learningStatus').html('<div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i>' + response.message + '</div>');
                    setTimeout(checkLearningStatus, 1000);
                } else {
                    $('#learningStatus').html('<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>' + response.message + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $('#learningStatus').html('<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Öğrenme durdurulamadı: ' + error + '</div>');
            }
        });
    }

    // İlk kontrol
    checkLearningStatus();
    
    // 5 saniyede bir yenile
    setInterval(checkLearningStatus, 5000);

    // Öğrenme işlemini başlat
    $('#startLearningBtn').click(function() {
        let wordLimit = $('#wordLimit').val();
        let manualWords = $('#manualWords').val();

        if (wordLimit < 1) {
            alert('Kelime limiti en az 1 olmalıdır.');
            return;
        }

        let data = {
            word_limit: wordLimit
        };

        if (manualWords.trim() !== '') {
            // Satır satır kelimeleri diziye dönüştür
            data.manual_words = manualWords.trim().split('\n').filter(w => w.trim() !== '');
        }

        $('#startLearningBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> İşlem başlatılıyor...');

        $.ajax({
            url: '/manage/learning/start',
            type: 'POST',
                headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: data,
            dataType: 'json',
            success: function(response) {
                $('#startLearningBtn').prop('disabled', false).html('<i class="bi bi-play-fill me-1"></i> Öğrenmeyi Başlat');
                
                if (response.success) {
                    alert('Öğrenme işlemi başlatıldı.');
                    checkLearningStatus();
                } else {
                    alert('Hata: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                $('#startLearningBtn').prop('disabled', false).html('<i class="bi bi-play-fill me-1"></i> Öğrenmeyi Başlat');
                alert('Bir hata oluştu: ' + error);
            }
        });
    });

    // İstatistikleri getir
    function loadStats() {
        $.ajax({
            url: '/manage/learning/stats',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    let stats = response.data;
                    let html = '';

                    // Ana istatistikler
                    html += '<div class="row mb-4">';
                    html += '<div class="col-md-3">';
                    html += '<div class="card bg-primary text-white h-100">';
                    html += '<div class="card-body text-center">';
                    html += '<h5 class="card-title"><i class="bi bi-book-half me-1"></i>Toplam Kelime</h5>';
                    html += '<h3 class="mb-0">' + stats.total_words + '</h3>';
                    html += '</div></div></div>';

                    html += '<div class="col-md-3">';
                    html += '<div class="card bg-success text-white h-100">';
                    html += '<div class="card-body text-center">';
                    html += '<h5 class="card-title"><i class="bi bi-collection me-1"></i>Toplam Kategori</h5>';
                    html += '<h3 class="mb-0">' + stats.total_categories + '</h3>';
                    html += '</div></div></div>';

                    html += '<div class="col-md-3">';
                    html += '<div class="card bg-info text-white h-100">';
                    html += '<div class="card-body text-center">';
                    html += '<h5 class="card-title"><i class="bi bi-arrow-left-right me-1"></i>Kelime İlişkileri</h5>';
                    html += '<h3 class="mb-0">' + stats.total_relations + '</h3>';
                    html += '</div></div></div>';

                    html += '<div class="col-md-3">';
                    html += '<div class="card bg-warning text-dark h-100">';
                    html += '<div class="card-body text-center">';
                    html += '<h5 class="card-title"><i class="bi bi-database me-1"></i>AI Veri Boyutu</h5>';
                    html += '<h3 class="mb-0">' + stats.db_size + '</h3>';
                    html += '</div></div></div>';
                    html += '</div>';

                    // Kelime türleri tablosu
                    if (stats.word_types && stats.word_types.length > 0) {
                        html += '<div class="card mb-4">';
                        html += '<div class="card-header">Kelime Türleri Dağılımı</div>';
                        html += '<div class="card-body">';
                        html += '<div class="table-responsive">';
                        html += '<table class="table table-striped table-hover">';
                        html += '<thead><tr><th>Tür</th><th>Sayı</th><th>Yüzde</th></tr></thead>';
                        html += '<tbody>';
                        
                        stats.word_types.forEach(function(item) {
                            html += '<tr>';
                            html += '<td>' + item.type + '</td>';
                            html += '<td>' + item.count + '</td>';
                            html += '<td>' + item.percent + '%</td>';
                            html += '</tr>';
                        });
                        
                        html += '</tbody>';
                        html += '</table>';
                        html += '</div></div></div>';
                    }

                    // En çok ilişkili kelimeler
                    if (stats.top_related_words && stats.top_related_words.length > 0) {
                        html += '<div class="card mb-4">';
                        html += '<div class="card-header">En Çok İlişkili Kelimeler</div>';
                        html += '<div class="card-body">';
                        html += '<div class="table-responsive">';
                        html += '<table class="table table-striped table-hover">';
                        html += '<thead><tr><th>Kelime</th><th>İlişki Sayısı</th><th>En Güçlü İlişki</th></tr></thead>';
                        html += '<tbody>';
                        
                        stats.top_related_words.forEach(function(item) {
                            html += '<tr>';
                            html += '<td>' + item.word + '</td>';
                            html += '<td>' + item.relation_count + '</td>';
                            html += '<td>' + (item.strongest_relation ? item.strongest_relation : '-') + '</td>';
                            html += '</tr>';
                        });
                        
                        html += '</tbody>';
                        html += '</table>';
                        html += '</div></div></div>';
                    }

                    // En popüler kategoriler
                    if (stats.top_categories && stats.top_categories.length > 0) {
                        html += '<div class="card">';
                        html += '<div class="card-header">En Popüler Kategoriler</div>';
                        html += '<div class="card-body">';
                        html += '<div class="table-responsive">';
                        html += '<table class="table table-striped table-hover">';
                        html += '<thead><tr><th>Kategori</th><th>Kelime Sayısı</th><th>Yüzde</th></tr></thead>';
                        html += '<tbody>';
                        
                        stats.top_categories.forEach(function(item) {
                            html += '<tr>';
                            html += '<td>' + item.category + '</td>';
                            html += '<td>' + item.count + '</td>';
                            html += '<td>' + item.percent + '%</td>';
                            html += '</tr>';
                        });
                        
                        html += '</tbody>';
                        html += '</table>';
                        html += '</div></div></div>';
                    }

                    $('#statsContent').html(html);
                } else {
                    $('#statsContent').html('<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>' + response.message + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $('#statsContent').html('<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>İstatistikler yüklenemedi: ' + error + '</div>');
            }
        });
    }

    // Stats tab gösterildiğinde istatistikleri yükle
    $('#stats-tab').on('shown.bs.tab', function (e) {
        loadStats();
    });

    // Kelime öğrenme
    $('#learnWordForm').submit(function(e) {
        e.preventDefault();
        
        const word = $('#wordToLearn').val();
        
        if (word.length < 2) {
            alert('Kelime en az 2 karakter olmalıdır.');
            return;
        }
        
        $('#learnWordResult').html('<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Yükleniyor...</span></div>');
        
        $.ajax({
            url: '/manage/word/learn',
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: {
                word: word
            },
            dataType: 'json',
            success: function(response) {
                $('#learnWordResult').html('');
                
                if (response.success) {
                    let html = '<div class="alert alert-success mb-3"><i class="bi bi-check-circle-fill me-2"></i>' + response.message + '</div>';
                    
                    if (response.data) {
                        let data = response.data;
                        
                        html += '<div class="card">';
                        html += '<div class="card-header">Öğrenilen Kelime Bilgileri</div>';
                        html += '<div class="card-body">';
                        html += '<div class="table-responsive">';
                        html += '<table class="table table-bordered">';
                        
                        if (data.word) {
                            html += '<tr><th style="width: 150px;">Kelime</th><td>' + data.word + '</td></tr>';
                        }
                        
                        if (data.type) {
                            html += '<tr><th>Tür</th><td>' + data.type + '</td></tr>';
                        }
                        
                        if (data.definition) {
                            html += '<tr><th>Tanım</th><td>' + data.definition + '</td></tr>';
                        }
                        
                        if (data.category) {
                            html += '<tr><th>Kategori</th><td>' + data.category + '</td></tr>';
                        }
                        
                        if (data.example) {
                            html += '<tr><th>Örnek</th><td>' + data.example + '</td></tr>';
                        }
                        
                        if (data.relation_count) {
                            html += '<tr><th>İlişki Sayısı</th><td>' + data.relation_count + '</td></tr>';
                        }
                        
                        html += '</table>';
                        html += '</div>';
                        html += '</div>';
                        html += '</div>';
                    }
                    
                    $('#learnWordResult').html(html);
                } else {
                    $('#learnWordResult').html('<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>' + response.message + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $('#learnWordResult').html('<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Bir hata oluştu: ' + error + '</div>');
            }
        });
    });

    // Kelime arama
    $('#searchWordForm').submit(function(e) {
        e.preventDefault();
        
        const query = $('#wordToSearch').val().trim();
        
        if (query.length < 2) {
            alert('Arama metni en az 2 karakter olmalıdır.');
            return;
        }
        
        $('#searchWordResult').html('<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Yükleniyor...</span></div>');
        
        $.ajax({
            url: '/manage/word/search',
            type: 'GET',
            data: {
                query: query
            },
            dataType: 'json',
            success: function(response) {
                $('#searchWordResult').html('');
                
                if (response.success) {
                    if (response.data && response.data.length > 0) {
                        let html = '<div class="alert alert-success mb-3"><i class="bi bi-check-circle-fill me-2"></i>' + response.data.length + ' sonuç bulundu</div>';
                        
                        html += '<div class="accordion" id="searchResults">';
                        
                        response.data.forEach(function(word, index) {
                            html += '<div class="accordion-item">';
                            html += '<h2 class="accordion-header" id="heading' + index + '">';
                            html += '<button class="accordion-button ' + (index > 0 ? 'collapsed' : '') + '" type="button" data-bs-toggle="collapse" data-bs-target="#collapse' + index + '" aria-expanded="' + (index === 0 ? 'true' : 'false') + '" aria-controls="collapse' + index + '">';
                            html += word.word + ' <span class="badge bg-secondary ms-2">' + word.type + '</span>';
                            html += '</button>';
                            html += '</h2>';
                            
                            html += '<div id="collapse' + index + '" class="accordion-collapse collapse ' + (index === 0 ? 'show' : '') + '" aria-labelledby="heading' + index + '" data-bs-parent="#searchResults">';
                            html += '<div class="accordion-body">';
                            
                            html += '<div class="table-responsive">';
                            html += '<table class="table table-bordered">';
                            
                            if (word.definition) {
                                html += '<tr><th style="width: 150px;">Tanım</th><td>' + word.definition + '</td></tr>';
                            }
                            
                            if (word.category) {
                                html += '<tr><th>Kategori</th><td>' + word.category + '</td></tr>';
                            }
                            
                            if (word.example) {
                                html += '<tr><th>Örnek</th><td>' + word.example + '</td></tr>';
                            }
                            
                            if (word.relations && word.relations.length > 0) {
                                html += '<tr><th>İlişkiler</th><td>';
                                word.relations.forEach(function(relation) {
                                    html += '<span class="badge bg-info me-1 mb-1">' + relation + '</span>';
                                });
                                html += '</td></tr>';
                            }
                            
                            if (word.created_at) {
                                html += '<tr><th>Eklenme Tarihi</th><td>' + word.created_at + '</td></tr>';
                            }
                            
                            html += '</table>';
                            html += '</div>';
                            
                            html += '</div>';
                            html += '</div>';
                            html += '</div>';
                        });
                        
                        html += '</div>';
                        
                        $('#searchWordResult').html(html);
                    } else {
                        $('#searchWordResult').html('<div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill me-2"></i>Sonuç bulunamadı</div>');
                    }
                } else {
                    $('#searchWordResult').html('<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>' + response.message + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $('#searchWordResult').html('<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Bir hata oluştu: ' + error + '</div>');
            }
        });
    });

    // Öğrenme sistemini temizle
    $('#clearLearningBtn').click(function() {
        if (confirm('Bu işlem tüm öğrenilen kelimeleri, ilişkileri ve kategorileri silecektir. Devam etmek istediğinize emin misiniz?')) {
            $.ajax({
                url: '/manage/learning/clear',
                type: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    confirm: 'yes'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Öğrenme sistemi başarıyla temizlendi.');
                        // Yenile
                        window.location.reload();
                    } else {
                        alert('Hata: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    alert('Bir hata oluştu: ' + error);
                }
                });
        }
    });

    // Akıllı cümle oluşturma
    $('#generateSentencesForm').submit(function(e) {
        e.preventDefault();
        
        const word = $('#wordForSentences').val();
        const count = $('#sentenceCount').val();
        const saveChecked = $('#saveSentences').is(':checked');
        
        if (!word) {
            alert('Lütfen bir kelime girin');
            return;
        }
        
        $('#generateSentencesResult .sentences-list').html('<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Yükleniyor...</span></div>');
        
        $.ajax({
            url: '/api/manage/learning/generate-sentences',
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: {
                word: word,
                count: count,
                save: saveChecked ? "1" : "0"
            },
            success: function(response) {
                if (response.success) {
                    let html = '<div class="alert alert-success">' + response.message + '</div>';
                    html += '<ul class="list-group">';
                    
                    if (response.data.sentences.length > 0) {
                        response.data.sentences.forEach(function(sentence) {
                            html += '<li class="list-group-item">' + sentence + '</li>';
                        });
                    } else {
                        html += '<li class="list-group-item">Cümle oluşturulamadı</li>';
                    }
                    
                    html += '</ul>';
                    $('#generateSentencesResult .sentences-list').html(html);
                } else {
                    $('#generateSentencesResult .sentences-list').html('<div class="alert alert-danger">' + response.message + '</div>');
                }
            },
            error: function(xhr) {
                let errorMessage = 'Bir hata oluştu';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                $('#generateSentencesResult .sentences-list').html('<div class="alert alert-danger">' + errorMessage + '</div>');
            }
        });
    });

    // Otomatik cümle oluşturma
    $('#autoSentencesForm').submit(function(e) {
        e.preventDefault();
        
        const count = $('#autoWordCount').val();
        const saveChecked = $('#autoSaveSentences').is(':checked');
        
        $('#autoSentencesResult .auto-sentences-list').html('<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Yükleniyor...</span></div>');
        
        $.ajax({
            url: '/api/manage/learning/auto-sentences',
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: {
                count: count,
                save: saveChecked ? "1" : "0"
            },
            success: function(response) {
                if (response.success) {
                    let html = '<div class="alert alert-success">' + response.message + '</div>';
                    
                    if (Object.keys(response.data.sentences).length > 0) {
                        html += '<div class="accordion" id="autoSentencesAccordion">';
                        
                        let index = 0;
                        for (const word in response.data.sentences) {
                            const sentences = response.data.sentences[word];
                            
                            html += '<div class="accordion-item">';
                            html += '<h2 class="accordion-header" id="autoHeading' + index + '">';
                            html += '<button class="accordion-button ' + (index === 0 ? '' : 'collapsed') + '" type="button" data-bs-toggle="collapse" data-bs-target="#autoCollapse' + index + '" aria-expanded="' + (index === 0 ? 'true' : 'false') + '" aria-controls="autoCollapse' + index + '">';
                            html += word;
                            if (response.data.newly_learned.includes(word)) {
                                html += ' <span class="badge bg-success ms-2">Yeni Öğrenildi</span>';
                            }
                            html += '</button>';
                            html += '</h2>';
                            
                            html += '<div id="autoCollapse' + index + '" class="accordion-collapse collapse ' + (index === 0 ? 'show' : '') + '" aria-labelledby="autoHeading' + index + '" data-bs-parent="#autoSentencesAccordion">';
                            html += '<div class="accordion-body">';
                            
                            html += '<ul class="list-group">';
                            sentences.forEach(function(sentence) {
                                html += '<li class="list-group-item">' + sentence + '</li>';
                            });
                            html += '</ul>';
                            
                            html += '</div>';
                            html += '</div>';
                            html += '</div>';
                            
                            index++;
                        }
                        
                        html += '</div>';
                    } else {
                        html += '<div class="alert alert-warning">Hiç cümle oluşturulamadı</div>';
                    }
                    
                    $('#autoSentencesResult .auto-sentences-list').html(html);
                } else {
                    $('#autoSentencesResult .auto-sentences-list').html('<div class="alert alert-danger">' + response.message + '</div>');
                }
            },
            error: function(xhr) {
                let errorMessage = 'Bir hata oluştu';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                $('#autoSentencesResult .auto-sentences-list').html('<div class="alert alert-danger">' + errorMessage + '</div>');
            }
        });
    });
});
</script>
@endsection 
