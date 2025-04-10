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
                <div class="text-gray-500 text-sm">
                    <span id="typing-indicator" class="hidden">AI yazıyor...</span>
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

        chatForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const message = messageInput.value.trim();
            if (!message) return;

            // Kullanıcı mesajını ekle
            addMessage(message, 'user');
            messageInput.value = '';

            // Typing göstergesini göster
            typingIndicator.classList.remove('hidden');

            try {
                const response = await fetch('/api/ai/process', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ message })
                });

                const data = await response.json();

                // Typing göstergesini gizle
                typingIndicator.classList.add('hidden');

                if (data.status === 'success') {
                    // AI yanıtını ekle
                    addMessage(data.message, 'ai');
                } else {
                    addMessage(data.message || 'Üzgünüm, bir hata oluştu.', 'ai');
                }
            } catch (error) {
                typingIndicator.classList.add('hidden');
                addMessage('Bağlantı hatası oluştu.', 'ai');
            }
        });

        function addMessage(message, sender) {
            // Mesaj obje ise toString() içeriğini kullan, değilse doğrudan mesajı kullan
            let messageText = message;
            if (typeof message === 'object') {
                messageText = JSON.stringify(message);
            }

            const messageDiv = document.createElement('div');
            messageDiv.className = 'flex items-start ' + (sender === 'user' ? 'justify-end' : '');
            
            const icon = sender === 'user' ? 'user' : 'robot';
            const bgColor = sender === 'user' ? 'bg-green-100' : 'bg-blue-100';
            const iconBg = sender === 'user' ? 'bg-green-500' : 'bg-blue-500';
            
            messageDiv.innerHTML = `
                <div class="flex items-start ${sender === 'user' ? 'flex-row-reverse' : ''}">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 rounded-full ${iconBg} flex items-center justify-center">
                            <i class="fas fa-${icon} text-white"></i>
                        </div>
                    </div>
                    <div class="${sender === 'user' ? 'mr-3' : 'ml-3'} ${bgColor} rounded-lg p-3 max-w-[70%]">
                        <p class="text-gray-800">${messageText}</p>
                    </div>
                </div>
            `;
            
            messagesContainer.appendChild(messageDiv);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
    });
</script>
@endsection 