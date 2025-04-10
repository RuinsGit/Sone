@extends('layouts.app')

@section('title', 'SoneAI - Yönetim Paneli')

@section('styles')
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
@endsection

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">SoneAI Yönetim Paneli</h1>
                    <p class="text-gray-600">Sistem ayarları ve durumu</p>
                </div>
                <div class="flex space-x-4">
                    <a href="{{ route('chat') }}" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">
                        <i class="fas fa-comments mr-2"></i>Chat
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- System Status -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold mb-4">Sistem Durumu</h2>
                <div class="space-y-4">
                    <!-- Memory Status -->
                    <div>
                        <div class="flex justify-between mb-2">
                            <span class="text-gray-600">Hafıza Kullanımı</span>
                            <span class="text-gray-800 font-medium" id="memory-usage">0%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <div id="memory-usage-bar" class="bg-blue-600 h-2.5 rounded-full" style="width: 0%"></div>
                        </div>
                    </div>

                    <!-- Learning Progress -->
                    <div>
                        <div class="flex justify-between mb-2">
                            <span class="text-gray-600">Öğrenme İlerlemesi</span>
                            <span class="text-gray-800 font-medium" id="learning-progress">0%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <div id="learning-progress-bar" class="bg-green-600 h-2.5 rounded-full" style="width: 0%"></div>
                        </div>
                    </div>

                    <!-- Emotional State -->
                    <div>
                        <h3 class="text-gray-600 mb-2">Duygusal Durum</h3>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="bg-blue-50 p-2 rounded">
                                <span class="text-sm text-gray-600">Mutluluk</span>
                                <div id="happiness-level" class="text-lg font-semibold text-blue-600">0%</div>
                            </div>
                            <div class="bg-green-50 p-2 rounded">
                                <span class="text-sm text-gray-600">Merak</span>
                                <div id="curiosity-level" class="text-lg font-semibold text-green-600">0%</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold mb-4">Ayarlar</h2>
                <form id="settings-form" class="space-y-4">
                    @csrf
                    <!-- Learning Rate -->
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            Öğrenme Hızı
                        </label>
                        <input type="range" 
                               name="learning_rate" 
                               min="0" 
                               max="1" 
                               step="0.1" 
                               value="0.5"
                               class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                        <div class="text-right text-sm text-gray-600" id="learning-rate-value">0.5</div>
                    </div>

                    <!-- Memory Capacity -->
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            Hafıza Kapasitesi
                        </label>
                        <input type="number" 
                               name="memory_capacity" 
                               min="1000" 
                               max="1000000" 
                               value="100000"
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    </div>

                    <!-- Emotional Sensitivity -->
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            Duygusal Hassasiyet
                        </label>
                        <input type="range" 
                               name="emotional_sensitivity" 
                               min="0" 
                               max="1" 
                               step="0.1" 
                               value="0.7"
                               class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                        <div class="text-right text-sm text-gray-600" id="emotional-sensitivity-value">0.7</div>
                    </div>

                    <!-- Personality Traits -->
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            Kişilik Özellikleri
                        </label>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="checkbox" name="personality_traits[]" value="curious" class="mr-2">
                                <span>Meraklı</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="personality_traits[]" value="creative" class="mr-2">
                                <span>Yaratıcı</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="personality_traits[]" value="analytical" class="mr-2">
                                <span>Analitik</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="personality_traits[]" value="empathetic" class="mr-2">
                                <span>Empatik</span>
                            </label>
                        </div>
                    </div>

                    <button type="submit" 
                            class="w-full bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">
                        Ayarları Kaydet
                    </button>
                </form>
            </div>
        </div>

        <!-- Training Section -->
        <div class="bg-white rounded-lg shadow-md p-6 mt-6">
            <h2 class="text-xl font-semibold mb-4">Model Eğitimi ve Bakım</h2>
            <div class="flex flex-col space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600">Model eğitimini başlatın veya durdurun</p>
                        <p class="text-sm text-gray-500">Eğitim işlemi birkaç dakika sürebilir ve sistem kaynaklarını kullanır.</p>
                    </div>
                    <button id="start-training" 
                            class="bg-green-500 text-white px-6 py-2 rounded-lg hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200">
                        <i class="fas fa-play mr-2"></i>Eğitimi Başlat
                    </button>
                </div>
                
                <!-- Database Maintenance -->
                <div class="border-t pt-4 mt-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600">Veritabanı Bakımı</p>
                            <p class="text-sm text-gray-500">Veritabanını temizleyin ve optimize edin. Bu işlem performansı artırır ve veri limiti sorunlarını çözer.</p>
                        </div>
                        <div class="flex space-x-2">
                            <button id="db-clean" 
                                    class="bg-blue-500 text-white px-3 py-2 rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200">
                                <i class="fas fa-broom mr-1"></i>Temizle
                            </button>
                            <button id="db-optimize" 
                                    class="bg-purple-500 text-white px-3 py-2 rounded-lg hover:bg-purple-600 focus:outline-none focus:ring-2 focus:ring-purple-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200">
                                <i class="fas fa-bolt mr-1"></i>Optimize Et
                            </button>
                            <button id="db-reset" 
                                    class="bg-red-500 text-white px-3 py-2 rounded-lg hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200">
                                <i class="fas fa-trash mr-1"></i>Sıfırla
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Maintenance Status -->
                <div id="maintenance-status" class="hidden bg-blue-50 p-4 rounded-lg border border-blue-200 mt-2">
                    <div class="flex items-center">
                        <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-700 mr-3"></div>
                        <div class="flex-1">
                            <p class="font-medium text-blue-700">Bakım İşlemi Devam Ediyor</p>
                            <p class="text-sm text-blue-600">Bu işlem birkaç dakika sürebilir.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Maintenance Success -->
                <div id="maintenance-success" class="hidden bg-green-50 p-4 rounded-lg border border-green-200 mt-2">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 text-xl mr-3"></i>
                        <div>
                            <p class="font-medium text-green-700">Bakım Tamamlandı!</p>
                            <div id="maintenance-success-details" class="text-sm text-green-600"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Maintenance Error -->
                <div id="maintenance-error" class="hidden bg-red-50 p-4 rounded-lg border border-red-200 mt-2">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-500 text-xl mr-3"></i>
                        <div>
                            <p class="font-medium text-red-700">Bakım Başarısız!</p>
                            <p class="text-sm text-red-600 mb-2" id="maintenance-error-message">Bir hata oluştu.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Training indicators (keep existing code) -->
                <!-- Eğitim Durumu Göstergesi -->
                <div id="training-status" class="hidden bg-blue-50 p-4 rounded-lg border border-blue-200">
                    <div class="flex items-center">
                        <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-700 mr-3"></div>
                        <div class="flex-1">
                            <p class="font-medium text-blue-700">Eğitim İşlemi Devam Ediyor</p>
                            <p class="text-sm text-blue-600">Bu işlem birkaç dakika sürebilir. Lütfen sayfadan ayrılmayın.</p>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="w-full bg-blue-200 rounded-full h-2.5 mb-1">
                            <div id="training-progress-bar" class="bg-blue-700 h-2.5 rounded-full" style="width: 0%"></div>
                        </div>
                        <div class="text-right text-sm text-blue-600" id="training-progress-text">0%</div>
                    </div>
                </div>
                
                <!-- Eğitim Başarılı Göstergesi -->
                <div id="training-success" class="hidden bg-green-50 p-4 rounded-lg border border-green-200">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 text-xl mr-3"></i>
                        <div>
                            <p class="font-medium text-green-700">Eğitim Tamamlandı!</p>
                            <p class="text-sm text-green-600">Model başarıyla eğitildi ve kullanıma hazır.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Eğitim Hata Göstergesi -->
                <div id="training-error" class="hidden bg-red-50 p-4 rounded-lg border border-red-200">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-500 text-xl mr-3"></i>
                        <div>
                            <p class="font-medium text-red-700">Eğitim Başarısız!</p>
                            <p class="text-sm text-red-600 mb-2" id="training-error-message">Bir hata oluştu.</p>
                            <button id="show-error-details" class="text-xs text-red-700 underline">Hata Detayları</button>
                        </div>
                    </div>
                    <pre id="training-error-details" class="hidden mt-2 text-xs bg-red-100 p-2 rounded overflow-auto max-h-32"></pre>
                </div>
            </div>
        </div>

        <!-- Learning Activity -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
            <!-- Learned Patterns -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold mb-4">Öğrenilen Kalıplar</h2>
                <div id="learned-patterns" class="space-y-2 max-h-80 overflow-y-auto">
                    <!-- Dinamik olarak doldurulacak -->
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold mb-4">Son Aktiviteler</h2>
                <div id="recent-activities" class="space-y-2 max-h-80 overflow-y-auto">
                    <!-- Dinamik olarak doldurulacak -->
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Range input değerlerini güncelle
        const learningRateInput = document.querySelector('input[name="learning_rate"]');
        const learningRateValue = document.getElementById('learning-rate-value');
        learningRateInput.addEventListener('input', function() {
            learningRateValue.textContent = this.value;
        });

        const emotionalSensitivityInput = document.querySelector('input[name="emotional_sensitivity"]');
        const emotionalSensitivityValue = document.getElementById('emotional-sensitivity-value');
        emotionalSensitivityInput.addEventListener('input', function() {
            emotionalSensitivityValue.textContent = this.value;
        });

        // Ayarları kaydet
        const settingsForm = document.getElementById('settings-form');
        settingsForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch('/manage/update-settings', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    alert('Ayarlar başarıyla güncellendi');
                    updateSystemStatus(data.status);
                } else {
                    alert('Ayarlar güncellenirken bir hata oluştu');
                }
            });
        });

        // Veritabanı bakım işlemleri
        const maintenanceStatus = document.getElementById('maintenance-status');
        const maintenanceSuccess = document.getElementById('maintenance-success');
        const maintenanceError = document.getElementById('maintenance-error');
        const maintenanceErrorMessage = document.getElementById('maintenance-error-message');
        const maintenanceSuccessDetails = document.getElementById('maintenance-success-details');
        
        // Veritabanı temizleme
        document.getElementById('db-clean').addEventListener('click', function() {
            runDatabaseMaintenance('clean', this);
        });
        
        // Veritabanı optimizasyonu
        document.getElementById('db-optimize').addEventListener('click', function() {
            runDatabaseMaintenance('optimize', this);
        });
        
        // Veritabanı sıfırlama
        document.getElementById('db-reset').addEventListener('click', function() {
            if (confirm('DİKKAT: Bu işlem tüm veritabanını sıfırlayacak ve öğrenilen tüm veriler kaybolacaktır! Devam etmek istiyor musunuz?')) {
                runDatabaseMaintenance('reset', this);
            }
        });
        
        // Veritabanı bakım işlemini çalıştır
        function runDatabaseMaintenance(mode, button) {
            // Butonu devre dışı bırak ve yükleniyor göster
            const allButtons = document.querySelectorAll('#db-clean, #db-optimize, #db-reset');
            allButtons.forEach(btn => btn.disabled = true);
            
            // İşlem türüne göre yükleniyor mesajını ayarla
            const loadingMessages = {
                'clean': 'Temizleniyor...',
                'optimize': 'Optimize Ediliyor...',
                'reset': 'Sıfırlanıyor...'
            };
            
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>' + loadingMessages[mode];
            
            // Durum göstergelerini ayarla
            maintenanceStatus.classList.remove('hidden');
            maintenanceSuccess.classList.add('hidden');
            maintenanceError.classList.add('hidden');
            
            // CSRF token'ı al
            const token = document.querySelector('meta[name="csrf-token"]').content;
            
            // AJAX çağrısı yap
            fetch('/manage/db-maintenance', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ mode: mode })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Sunucu hatası: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                // Durum göstergelerini güncelle
                maintenanceStatus.classList.add('hidden');
                
                if (data.success) {
                    // Başarılı ise
                    maintenanceSuccess.classList.remove('hidden');
                    maintenanceSuccessDetails.innerHTML = data.output ? data.output.replace(/\n/g, '<br>') : 'İşlem başarıyla tamamlandı.';
                    
                    // Sistem durumunu güncelle
                    updateSystemStatus();
                } else {
                    // Başarısız ise
                    maintenanceError.classList.remove('hidden');
                    maintenanceErrorMessage.textContent = data.message || 'Bilinmeyen hata';
                }
                
                // Butonu normal haline getir
                const buttonText = {
                    'clean': '<i class="fas fa-broom mr-1"></i>Temizle',
                    'optimize': '<i class="fas fa-bolt mr-1"></i>Optimize Et',
                    'reset': '<i class="fas fa-trash mr-1"></i>Sıfırla'
                };
                
                button.innerHTML = buttonText[mode];
                allButtons.forEach(btn => btn.disabled = false);
            })
            .catch(error => {
                // Hata durumunda
                maintenanceStatus.classList.add('hidden');
                maintenanceError.classList.remove('hidden');
                maintenanceErrorMessage.textContent = error.message || 'Bilinmeyen hata';
                
                // Butonu normal haline getir
                const buttonText = {
                    'clean': '<i class="fas fa-broom mr-1"></i>Temizle',
                    'optimize': '<i class="fas fa-bolt mr-1"></i>Optimize Et',
                    'reset': '<i class="fas fa-trash mr-1"></i>Sıfırla'
                };
                
                button.innerHTML = buttonText[mode];
                allButtons.forEach(btn => btn.disabled = false);
            });
        }

        // Sistem durumunu güncelle
        function updateSystemStatus() {
            fetch('/api/ai/status')
                .then(response => response.json())
                .then(data => {
                    // Hafıza ve öğrenme durumunu güncelle
                    const memoryUsage = document.getElementById('memory-usage');
                    const memoryUsageBar = document.getElementById('memory-usage-bar');
                    const learningProgress = document.getElementById('learning-progress');
                    const learningProgressBar = document.getElementById('learning-progress-bar');
                    const happinessLevel = document.getElementById('happiness-level');
                    const curiosityLevel = document.getElementById('curiosity-level');

                    memoryUsage.textContent = data.memory_usage + '%';
                    memoryUsageBar.style.width = data.memory_usage + '%';
                    learningProgress.textContent = data.learning_progress + '%';
                    learningProgressBar.style.width = data.learning_progress + '%';
                    
                    if(data.emotional_state) {
                        happinessLevel.textContent = Math.round(data.emotional_state.happiness * 100) + '%';
                        curiosityLevel.textContent = Math.round(data.emotional_state.curiosity * 100) + '%';
                    }

                    // Öğrenilen kalıpları güncelle
                    if(data.learned_patterns) {
                        const learnedPatternsContainer = document.getElementById('learned-patterns');
                        learnedPatternsContainer.innerHTML = '';
                        
                        data.learned_patterns.forEach(pattern => {
                            const div = document.createElement('div');
                            div.className = 'p-2 bg-gray-50 rounded flex justify-between items-center';
                            div.innerHTML = `
                                <span class="text-gray-700">${pattern.word}</span>
                                <span class="text-sm text-gray-500">Frekans: ${pattern.frequency}</span>
                            `;
                            learnedPatternsContainer.appendChild(div);
                        });
                    }

                    // Son aktiviteleri güncelle
                    if(data.recent_activities) {
                        const recentActivitiesContainer = document.getElementById('recent-activities');
                        recentActivitiesContainer.innerHTML = '';
                        
                        data.recent_activities.forEach(activity => {
                            const div = document.createElement('div');
                            div.className = 'p-2 bg-gray-50 rounded';
                            div.innerHTML = `
                                <div class="text-sm text-gray-700">${activity.description}</div>
                                <div class="text-xs text-gray-500">${activity.timestamp}</div>
                            `;
                            recentActivitiesContainer.appendChild(div);
                        });
                    }
                });
        }

        // Her 30 saniyede bir durumu güncelle
        setInterval(updateSystemStatus, 30000);
        updateSystemStatus();

        // Eğitim durumu göstergeleri
        const trainingStatus = document.getElementById('training-status');
        const trainingSuccess = document.getElementById('training-success');
        const trainingError = document.getElementById('training-error');
        const trainingErrorMessage = document.getElementById('training-error-message');
        const trainingErrorDetails = document.getElementById('training-error-details');
        const showErrorDetailsBtn = document.getElementById('show-error-details');
        const trainingProgressBar = document.getElementById('training-progress-bar');
        const trainingProgressText = document.getElementById('training-progress-text');
        
        // Hata detaylarını göster/gizle
        showErrorDetailsBtn && showErrorDetailsBtn.addEventListener('click', function() {
            trainingErrorDetails.classList.toggle('hidden');
        });
        
        // Eğitim durumunu kontrol et
        function checkTrainingStatus() {
            fetch('/manage/train-status')
                .then(response => response.json())
                .then(data => {
                    if (data.is_training) {
                        // Eğitim devam ediyor
                        trainingStatus.classList.remove('hidden');
                        trainingSuccess.classList.add('hidden');
                        trainingError.classList.add('hidden');
                        
                        // İlerleme durumunu güncelle
                        const progress = data.learning_progress || 0;
                        trainingProgressBar.style.width = progress + '%';
                        trainingProgressText.textContent = progress + '%';
                        
                        // 3 saniye sonra tekrar kontrol et
                        setTimeout(checkTrainingStatus, 3000);
                    } else {
                        // Eğitim bitti veya hiç başlamadı
                        trainingStatus.classList.add('hidden');
                    }
                })
                .catch(error => {
                    console.error('Eğitim durumu kontrolü hatası:', error);
                });
        }

        // Eğitim başlatma
        document.getElementById('start-training').addEventListener('click', function() {
            // Butonu devre dışı bırak ve yükleniyor göster
            const button = this;
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Eğitim Başlatılıyor...';
            
            // Durum göstergelerini sıfırla
            trainingStatus.classList.remove('hidden');
            trainingSuccess.classList.add('hidden');
            trainingError.classList.add('hidden');
            trainingProgressBar.style.width = '0%';
            trainingProgressText.textContent = '0%';

            // CSRF token'ı al
            const token = document.querySelector('meta[name="csrf-token"]').content;
            
            // AJAX çağrısı yap
            fetch('/manage/train', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    learning_rate: document.querySelector('input[name="learning_rate"]').value || 0.1
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Sunucu hatası: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if(data.success) {
                    // Başarılı ise durumu göster
                    console.log('Eğitim başlatıldı:', data);
                    
                    // Eğitim durumunu düzenli olarak kontrol et
                    checkTrainingStatus();
                    
                    // Sistem durumunu güncelle
                    updateSystemStatus();
                    
                    // Butonu aktif hale getir ve metnini güncelle
                    button.disabled = false;
                    button.innerHTML = '<i class="fas fa-play mr-2"></i>Eğitimi Başlat';
                } else {
                    // Başarısız ise hata göster
                    console.error('Eğitim hatası:', data.message);
                    
                    // Hata durumunu göster
                    trainingStatus.classList.add('hidden');
                    trainingSuccess.classList.add('hidden');
                    trainingError.classList.remove('hidden');
                    trainingErrorMessage.textContent = data.message || 'Bilinmeyen hata';
                    
                    if (data.trace) {
                        trainingErrorDetails.textContent = data.trace;
                    }
                    
                    // Butonu aktif hale getir ve metnini güncelle
                    button.disabled = false;
                    button.innerHTML = '<i class="fas fa-play mr-2"></i>Eğitimi Başlat';
                }
            })
            .catch(error => {
                // Hata durumunda uyarı göster
                console.error('Eğitim hatası:', error);
                
                // Hata durumunu göster
                trainingStatus.classList.add('hidden');
                trainingSuccess.classList.add('hidden');
                trainingError.classList.remove('hidden');
                trainingErrorMessage.textContent = error.message || 'Bilinmeyen hata';
                
                // Butonu aktif hale getir ve metnini güncelle
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-play mr-2"></i>Eğitimi Başlat';
            });
        });
    });
</script>
@endsection 