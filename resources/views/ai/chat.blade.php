@extends('layouts.app')

@section('title', 'SoneAI - Yapay Zeka Sohbet')

@section('styles')
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
@endsection

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h1 class="text-3xl font-bold text-gray-800">SoneAI</h1>
            <p class="text-gray-600">Yapay Zeka Asistanı</p>
        </div>

        <!-- Chat Container -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6 h-[600px] flex flex-col">
            <!-- Messages Area -->
            <div id="messages" class="flex-1 overflow-y-auto mb-4 space-y-4">
                <!-- AI Message -->
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center">
                            <i class="fas fa-robot text-white"></i>
                        </div>
                    </div>
                    <div class="ml-3 bg-blue-100 rounded-lg p-3 max-w-[70%]">
                        <p class="text-gray-800">Merhaba! Ben SoneAI. Size nasıl yardımcı olabilirim?</p>
                    </div>
                </div>
            </div>

            <!-- Input Area -->
            <div class="border-t pt-4">
                <form id="chat-form" class="flex space-x-4">
                    <input type="text" 
                           id="message-input" 
                           class="flex-1 border rounded-lg px-4 py-2 focus:outline-none focus:border-blue-500"
                           placeholder="Mesajınızı yazın...">
                    <button type="submit" 
                            class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600 focus:outline-none">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
            </div>
        </div>

        <!-- Status Bar -->
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                    <span class="text-gray-600">Sistem Aktif</span>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center">
                        <label for="creative-toggle" class="mr-2 text-sm text-gray-600">Yaratıcı Mod</label>
                        <div class="relative inline-block w-10 align-middle select-none">
                            <input type="checkbox" name="creative-toggle" id="creative-toggle" class="absolute block w-5 h-5 rounded-full bg-white border-2 appearance-none cursor-pointer focus:outline-none focus:ring-2 focus:ring-blue-300 checked:right-0 checked:bg-blue-500 checked:border-blue-500 transition-all duration-200 ease-in-out"/>
                            <label for="creative-toggle" class="block h-5 overflow-hidden bg-gray-300 rounded-full cursor-pointer"></label>
                        </div>
                    </div>
                    <div class="text-gray-500 text-sm">
                        <span id="typing-indicator" class="hidden">AI yazıyor...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const chatForm = document.getElementById('chat-form');
        const messageInput = document.getElementById('message-input');
        const messagesContainer = document.getElementById('messages');
        const typingIndicator = document.getElementById('typing-indicator');
        const creativeToggle = document.getElementById('creative-toggle');
        
        // Sayfa yüklendiğinde Creative Mode durumunu kontrol et
        if (localStorage.getItem('creative_mode') === 'true') {
            creativeToggle.checked = true;
        }
        
        // Mesaj göndermeden önce form kontrolü
        if (!chatForm) {
            console.error('Chat form bulunamadı!');
        } else {
            chatForm.addEventListener('submit', handleSubmit);
        }
        
        // Yaratıcı mod değişikliğini sakla
        if (creativeToggle) {
            creativeToggle.addEventListener('change', function() {
                localStorage.setItem('creative_mode', this.checked);
            });
        } else {
            console.error('Creative toggle bulunamadı!');
        }

        async function handleSubmit(e) {
            e.preventDefault();
            
            if (!messageInput) {
                console.error('Mesaj input alanı bulunamadı!');
                return;
            }
            
            const message = messageInput.value.trim();
            if (!message) return;

            // Kullanıcı mesajını ekle
            addMessage(message, 'user');
            messageInput.value = '';

            // Typing göstergesini göster
            if (typingIndicator) {
                typingIndicator.classList.remove('hidden');
            }

            // Timeout ekle - yanıt alınamazsa 15 saniye sonra hata göster
            const timeout = setTimeout(() => {
                if (typingIndicator) {
                    typingIndicator.classList.add('hidden');
                }
                addMessage('Yanıt alınamadı. Lütfen tekrar deneyin.', 'ai');
            }, 15000);

            try {
                // CSRF token kontrolü
                const csrfToken = document.querySelector('meta[name="csrf-token"]');
                if (!csrfToken) {
                    throw new Error('CSRF token bulunamadı!');
                }
                
                // Creative mod durumunu güvenli şekilde kontrol et
                const isCreativeMode = creativeToggle && creativeToggle.checked;
                
                const response = await fetch('/api/ai/process', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken.content
                    },
                    body: JSON.stringify({ 
                        message, 
                        creative_mode: isCreativeMode 
                    })
                });
                
                // Timeout'u temizle
                clearTimeout(timeout);

                // HTTP yanıt durumunu kontrol et
                if (!response.ok) {
                    throw new Error(`Sunucu hatası: ${response.status} - ${response.statusText}`);
                }

                const data = await response.json();

                // Typing göstergesini gizle
                if (typingIndicator) {
                    typingIndicator.classList.add('hidden');
                }

                if (data.success) {
                    // AI yanıtını ekle
                    addMessage(data.response, 'ai');
                } else if (data.error) {
                    // Sunucudan dönen özel hata mesajı
                    addMessage(data.error, 'ai');
                } else {
                    // Genel hata durumu
                    addMessage(data.response || 'Üzgünüm, bir hata oluştu.', 'ai');
                }
            } catch (error) {
                // Timeout'u temizle
                clearTimeout(timeout);
                
                // Hata detaylarını logla
                console.error('Hata:', error);
                
                // Typing göstergesini gizle
                if (typingIndicator) {
                    typingIndicator.classList.add('hidden');
                }
                
                // Kullanıcıya hata mesajı göster
                addMessage('Bağlantı hatası oluştu. Lütfen internet bağlantınızı kontrol edin ve tekrar deneyin.', 'ai');
            }
        }

        function addMessage(message, sender) {
            if (!messagesContainer) {
                console.error('Mesaj konteyneri bulunamadı!');
                return;
            }
            
            // Mesaj kontrolü
            if (!message) {
                message = sender === 'user' ? 
                    'Mesaj gönderilirken bir sorun oluştu.' : 
                    'Yanıt alınamadı. Lütfen tekrar deneyin.';
            }
            
            // Mesaj obje ise toString() içeriğini kullan, değilse doğrudan mesajı kullan
            let messageText = message;
            if (typeof message === 'object') {
                try {
                    messageText = JSON.stringify(message);
                } catch (e) {
                    messageText = "Mesaj içeriği gösterilemiyor.";
                }
            }

            const messageDiv = document.createElement('div');
            messageDiv.className = 'flex items-start ' + (sender === 'user' ? 'justify-end' : '');
            
            const icon = sender === 'user' ? 'user' : 'robot';
            const bgColor = sender === 'user' ? 'bg-green-100' : 'bg-blue-100';
            const iconBg = sender === 'user' ? 'bg-green-500' : 'bg-blue-500';
            
            // Mesaj metninde satır sonlarını <br> etiketlerine dönüştür
            messageText = String(messageText).replace(/\n/g, '<br>');
            
            // XSS koruması için basit bir metin temizleme (HTML entity dönüşümü)
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            
            // <br> etiketlerini koruyarak HTML'i escape et
            let safeText = escapeHtml(messageText).replace(/&lt;br&gt;/g, '<br>');
            
            messageDiv.innerHTML = `
                <div class="flex items-start ${sender === 'user' ? 'flex-row-reverse' : ''}">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 rounded-full ${iconBg} flex items-center justify-center">
                            <i class="fas fa-${icon} text-white"></i>
                        </div>
                    </div>
                    <div class="${sender === 'user' ? 'mr-3' : 'ml-3'} ${bgColor} rounded-lg p-3 max-w-[70%]">
                        <p class="text-gray-800">${safeText}</p>
                    </div>
                </div>
            `;
            
            messagesContainer.appendChild(messageDiv);
            
            // Scroll to bottom
            setTimeout(() => {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }, 10);
        }
    });
</script>
@endsection 