/**
 * Voice Assistant for Sikkerjob
 * Uses OpenAI Whisper for Danish speech recognition
 */

(function() {
    'use strict';

    class VoiceAssistant {
        constructor() {
            this.mediaRecorder = null;
            this.audioChunks = [];
            this.isRecording = false;
            this.stream = null;
            this.commands = [];
            this.ui = null;
            this.currentRole = '';
            this.transcriptTimeout = null;
            // Conversation mode
            this.conversationMode = false;
            this.conversationTimeout = null;
            this.currentAudio = null;
            this.CONVERSATION_TIMEOUT = 30000; // 30 seconds of silence ends conversation
        }

        init() {
            // Check for required APIs
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                console.warn('[Voice] getUserMedia not supported');
                return false;
            }

            this.setupUI();
            this.registerCommands();
            console.log('[Voice] Assistant initialized');
            return true;
        }

        setupUI() {
            const widget = document.getElementById('voiceWidget');
            const button = document.getElementById('voiceToggle');
            const transcript = document.getElementById('voiceTranscript');

            if (!widget || !button) {
                console.warn('[Voice] Widget elements not found');
                return;
            }

            this.ui = { widget, button, transcript };

            // Toggle recording on click
            button.addEventListener('click', () => this.toggleRecording());

            // Long press for continuous recording (mobile)
            let pressTimer = null;
            button.addEventListener('touchstart', (e) => {
                e.preventDefault();
                pressTimer = setTimeout(() => {
                    if (!this.isRecording) {
                        this.startRecording();
                    }
                }, 500);
            });

            button.addEventListener('touchend', () => {
                clearTimeout(pressTimer);
                if (this.isRecording) {
                    this.stopRecording();
                }
            });
        }

        registerCommands() {
            // Get base path for navigation
            const basePath = this.getBasePath();

            // === GODKENDELSE ===
            this.addCommand(/godkend (?:ptw )?(\S+)/i, (match) => {
                this.approveByVoice(match[1]);
            }, 'PTW godkendt');

            this.addCommand(/godkend (?:ptw )?(\S+) som (\w+)/i, (match) => {
                this.approveByVoice(match[1], match[2]);
            }, 'PTW godkendt');

            // === NAVIGATION ===
            this.addCommand(/vis aktive/i, () => {
                this.filterByStatus('aktive');
            }, 'Viser aktive');

            this.addCommand(/vis planlagte/i, () => {
                this.filterByStatus('planlagte');
            }, 'Viser planlagte');

            this.addCommand(/vis afsluttede/i, () => {
                this.filterByStatus('afsluttede');
            }, 'Viser afsluttede');

            this.addCommand(/vis alle/i, () => {
                this.filterByStatus('alle');
            }, 'Viser alle');

            this.addCommand(/opret ny|ny ptw/i, () => {
                window.location.href = basePath + 'create_wo.php';
            }, 'Går til opret ny');

            this.addCommand(/dashboard/i, () => {
                window.location.href = basePath + 'dashboard.php';
            }, 'Går til dashboard');

            this.addCommand(/kort|kortet/i, () => {
                window.location.href = basePath + 'map_wo.php';
            }, 'Går til kort');

            this.addCommand(/hjem|oversigt|liste/i, () => {
                window.location.href = basePath + 'view_wo.php';
            }, 'Går til oversigt');

            // === SØGNING ===
            this.addCommand(/søg (?:efter )?(.+)/i, (match) => {
                this.searchPTW(match[1]);
            }, 'Søger');

            // === LISTE NAVIGATION ===
            this.addCommand(/næste/i, () => {
                this.scrollToNext();
            }, 'Næste');

            this.addCommand(/forrige/i, () => {
                this.scrollToPrev();
            }, 'Forrige');

            this.addCommand(/vis detaljer|detaljer/i, () => {
                this.showDetails();
            }, 'Viser detaljer');

            // === SYSTEM ===
            this.addCommand(/hjælp|kommandoer/i, () => {
                this.showHelp();
            });

            this.addCommand(/stop|luk|farvel|slut/i, () => {
                this.endConversation();
            });

            // Start conversation mode
            this.addCommand(/samtale|snak med mig|start samtale/i, () => {
                this.startConversation();
            });
        }

        addCommand(pattern, handler, feedback = null) {
            this.commands.push({ pattern, handler, feedback });
        }

        getBasePath() {
            const path = window.location.pathname;
            if (path.includes('/admin/')) {
                return '../';
            }
            return '';
        }

        async toggleRecording() {
            if (this.isRecording) {
                this.stopRecording();
            } else {
                // Double-click starts conversation mode
                await this.startRecording();
            }
        }

        // === CONVERSATION MODE ===

        startConversation() {
            this.conversationMode = true;
            this.resetConversationTimeout();
            this.updateUI('conversation');
            this.showTranscript('Samtale startet - sig "stop" for at afslutte');
            this.speak('Samtale startet. Jeg lytter. Sig stop når du er færdig.');
        }

        endConversation() {
            this.conversationMode = false;
            clearTimeout(this.conversationTimeout);
            this.hideTranscript();
            this.updateUI('idle');
            this.speak('Samtale afsluttet');
        }

        resetConversationTimeout() {
            clearTimeout(this.conversationTimeout);
            if (this.conversationMode) {
                this.conversationTimeout = setTimeout(() => {
                    this.showTranscript('Samtale timeout - ingen aktivitet');
                    this.speak('Jeg hørte ikke noget. Samtalen afsluttes.');
                    this.endConversation();
                }, this.CONVERSATION_TIMEOUT);
            }
        }

        continueConversation() {
            if (this.conversationMode && !this.isRecording) {
                this.resetConversationTimeout();
                // Small delay before listening again
                setTimeout(() => {
                    if (this.conversationMode) {
                        this.startRecording();
                    }
                }, 500);
            }
        }

        async startRecording() {
            try {
                // Request microphone permission
                this.stream = await navigator.mediaDevices.getUserMedia({
                    audio: {
                        echoCancellation: true,
                        noiseSuppression: true,
                    }
                });

                // Determine supported mime type
                let mimeType = 'audio/webm';
                if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
                    mimeType = 'audio/webm;codecs=opus';
                } else if (MediaRecorder.isTypeSupported('audio/mp4')) {
                    mimeType = 'audio/mp4';
                }

                this.mediaRecorder = new MediaRecorder(this.stream, { mimeType });
                this.audioChunks = [];

                this.mediaRecorder.ondataavailable = (e) => {
                    if (e.data.size > 0) {
                        this.audioChunks.push(e.data);
                    }
                };

                this.mediaRecorder.onstop = () => {
                    this.processAudio();
                };

                this.mediaRecorder.start();
                this.isRecording = true;
                this.updateUI('listening');
                this.showTranscript('Lytter...');

                console.log('[Voice] Recording started');

            } catch (error) {
                console.error('[Voice] Failed to start recording:', error);
                this.showError('Kunne ikke få adgang til mikrofon');
            }
        }

        stopRecording() {
            if (this.mediaRecorder && this.isRecording) {
                this.mediaRecorder.stop();
                this.isRecording = false;
                this.updateUI('processing');
                this.showTranscript('Behandler...');

                // Stop all audio tracks
                if (this.stream) {
                    this.stream.getTracks().forEach(track => track.stop());
                }

                console.log('[Voice] Recording stopped');
            }
        }

        async processAudio() {
            if (this.audioChunks.length === 0) {
                this.showError('Ingen lyd optaget');
                return;
            }

            const audioBlob = new Blob(this.audioChunks, { type: 'audio/webm' });
            const formData = new FormData();
            formData.append('audio', audioBlob, 'recording.webm');

            try {
                const basePath = this.getBasePath();
                const response = await fetch(basePath + 'api/voice-transcribe.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success && result.text) {
                    this.currentRole = result.role || '';
                    this.showTranscript(`"${result.text}"`);
                    this.executeCommand(result.text);
                } else {
                    this.showError(result.error || 'Kunne ikke genkende tale');
                }

            } catch (error) {
                console.error('[Voice] Processing error:', error);
                this.showError('Fejl ved behandling');
            }
        }

        executeCommand(text) {
            const normalizedText = text.toLowerCase().trim();

            // Check if it's a question - send to AI
            if (this.isQuestion(normalizedText)) {
                this.askAI(text);
                return true;
            }

            for (const cmd of this.commands) {
                const match = normalizedText.match(cmd.pattern);
                if (match) {
                    console.log('[Voice] Command matched:', cmd.pattern);
                    try {
                        cmd.handler(match);
                        if (cmd.feedback) {
                            this.speak(cmd.feedback);
                        }
                        this.updateUI('success');
                        this.autoHideTranscript();
                        return true;
                    } catch (error) {
                        console.error('[Voice] Command execution error:', error);
                        this.showError('Kommando fejlede');
                        return false;
                    }
                }
            }

            // No command matched - try AI as fallback
            this.askAI(text);
            return true;
        }

        isQuestion(text) {
            // Danish question words and patterns
            const questionPatterns = [
                /^hvad\b/i,
                /^hvordan\b/i,
                /^hvorfor\b/i,
                /^hvornår\b/i,
                /^hvor\b/i,
                /^hvem\b/i,
                /^hvilken?\b/i,
                /^kan jeg\b/i,
                /^kan man\b/i,
                /^er det\b/i,
                /^har jeg\b/i,
                /^må jeg\b/i,
                /^skal jeg\b/i,
                /^fortæl\b/i,
                /^forklar\b/i,
                /\?$/,
            ];

            return questionPatterns.some(pattern => pattern.test(text));
        }

        async askAI(question) {
            this.showTranscript('Tænker...');
            this.updateUI('processing');

            const basePath = this.getBasePath();

            try {
                const response = await fetch(basePath + 'api/voice-ask.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ question: question })
                });

                const result = await response.json();

                if (result.success && result.answer) {
                    // Show and speak the answer
                    this.showTranscript(result.answer);
                    this.speak(result.answer);
                    this.updateUI('success');
                    // Keep transcript visible longer for answers
                    clearTimeout(this.transcriptTimeout);
                    this.transcriptTimeout = setTimeout(() => {
                        this.hideTranscript();
                    }, 15000);
                } else {
                    this.showError(result.error || 'Kunne ikke finde svar');
                }

            } catch (error) {
                console.error('[Voice] AI error:', error);
                this.showError('Fejl ved AI-svar');
            }
        }

        // === COMMAND HANDLERS ===

        approveByVoice(woNumber, role = null) {
            // Find matching approval button
            const targetRole = role || this.currentRole;

            // Try to find button by work order number
            const buttons = document.querySelectorAll('.ajax-approve-btn, .btn-approve');
            let found = false;

            for (const btn of buttons) {
                const card = btn.closest('.work-permit-card, .ptw-card, [data-wo-id]');
                if (!card) continue;

                const woId = card.dataset.woId || card.querySelector('[data-id]')?.dataset.id;
                const cardNumber = card.querySelector('.wo-number, .ptw-number')?.textContent?.trim();

                // Match by ID or number
                if (woId === woNumber || cardNumber?.includes(woNumber)) {
                    // Check role match
                    const btnRole = btn.dataset.role;
                    if (!targetRole || btnRole === targetRole) {
                        btn.click();
                        found = true;
                        break;
                    }
                }
            }

            if (!found) {
                this.speak('PTW ikke fundet');
                this.showTranscript(`PTW ${woNumber} ikke fundet`);
            }
        }

        filterByStatus(status) {
            // Try clicking filter checkboxes
            const checkboxes = document.querySelectorAll('.filter-controls input[type="checkbox"]');

            if (status === 'alle') {
                // Check all filters
                checkboxes.forEach(cb => { cb.checked = true; });
                // Trigger change event
                if (checkboxes[0]) {
                    checkboxes[0].dispatchEvent(new Event('change', { bubbles: true }));
                }
            } else {
                // Map Danish status to checkbox
                const statusMap = {
                    'aktive': 'active',
                    'planlagte': 'planned',
                    'afsluttede': 'completed'
                };

                // Uncheck all, then check the matching one
                checkboxes.forEach(cb => {
                    const label = cb.parentElement?.textContent?.toLowerCase() || '';
                    if (status === 'aktive' && (label.includes('aktiv') || label.includes('active'))) {
                        cb.checked = true;
                    } else if (status === 'planlagte' && (label.includes('planlagt') || label.includes('planned'))) {
                        cb.checked = true;
                    } else if (status === 'afsluttede' && (label.includes('afslut') || label.includes('completed'))) {
                        cb.checked = true;
                    } else {
                        cb.checked = false;
                    }
                });

                if (checkboxes[0]) {
                    checkboxes[0].dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
        }

        searchPTW(query) {
            // Find search input
            const searchInput = document.querySelector('#search, .search-input, input[type="search"]');
            if (searchInput) {
                searchInput.value = query;
                searchInput.dispatchEvent(new Event('input', { bubbles: true }));
                searchInput.focus();
            } else {
                this.speak('Søgefelt ikke fundet');
            }
        }

        scrollToNext() {
            const cards = document.querySelectorAll('.work-permit-card, .ptw-card');
            if (cards.length === 0) return;

            // Find currently visible card
            let nextCard = null;
            for (let i = 0; i < cards.length; i++) {
                const rect = cards[i].getBoundingClientRect();
                if (rect.top > window.innerHeight * 0.3) {
                    nextCard = cards[i];
                    break;
                }
            }

            if (nextCard) {
                nextCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        scrollToPrev() {
            const cards = document.querySelectorAll('.work-permit-card, .ptw-card');
            if (cards.length === 0) return;

            // Find card above current view
            let prevCard = null;
            for (let i = cards.length - 1; i >= 0; i--) {
                const rect = cards[i].getBoundingClientRect();
                if (rect.bottom < window.innerHeight * 0.3) {
                    prevCard = cards[i];
                    break;
                }
            }

            if (prevCard) {
                prevCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        showDetails() {
            // Find centered/focused card and click its view button
            const cards = document.querySelectorAll('.work-permit-card, .ptw-card');
            for (const card of cards) {
                const rect = card.getBoundingClientRect();
                if (rect.top > 0 && rect.top < window.innerHeight * 0.5) {
                    const viewBtn = card.querySelector('.btn-view, a[href*="print_wo"]');
                    if (viewBtn) {
                        viewBtn.click();
                        return;
                    }
                }
            }
        }

        showHelp() {
            const helpText = `
Stemmekommandoer:
• "Samtale" - Start løbende samtale
• "Godkend [nummer]" - Godkend PTW
• "Vis aktive/planlagte/afsluttede"
• "Søg efter [tekst]"
• "Dashboard", "Kort", "Opret ny"
• "Stop" - Afslut samtale

Spørg om hvad som helst:
• "Hvad betyder status aktiv?"
• "Hvordan godkender jeg en PTW?"
            `.trim();

            this.showTranscript(helpText);
            this.speak('Sig samtale for løbende dialog. Du kan også stille spørgsmål. Sig stop for at afslutte.');
        }

        // === UI METHODS ===

        updateUI(state) {
            if (!this.ui?.button) return;

            this.ui.button.classList.remove('listening', 'processing', 'success', 'error', 'conversation');

            switch (state) {
                case 'listening':
                    this.ui.button.classList.add('listening');
                    if (this.conversationMode) {
                        this.ui.button.classList.add('conversation');
                    }
                    break;
                case 'processing':
                    this.ui.button.classList.add('processing');
                    if (this.conversationMode) {
                        this.ui.button.classList.add('conversation');
                    }
                    break;
                case 'conversation':
                    this.ui.button.classList.add('conversation');
                    break;
                case 'success':
                    this.ui.button.classList.add('success');
                    if (this.conversationMode) {
                        this.ui.button.classList.add('conversation');
                    }
                    if (!this.conversationMode) {
                        setTimeout(() => this.updateUI('idle'), 2000);
                    }
                    break;
                case 'error':
                    this.ui.button.classList.add('error');
                    if (!this.conversationMode) {
                        setTimeout(() => this.updateUI('idle'), 3000);
                    } else {
                        setTimeout(() => this.updateUI('conversation'), 3000);
                    }
                    break;
                default:
                    // idle
                    break;
            }
        }

        showTranscript(text) {
            if (!this.ui?.transcript) return;

            this.ui.transcript.textContent = text;
            this.ui.transcript.classList.add('active');

            // Clear any pending auto-hide
            clearTimeout(this.transcriptTimeout);
        }

        hideTranscript() {
            if (!this.ui?.transcript) return;
            this.ui.transcript.classList.remove('active');
        }

        autoHideTranscript() {
            clearTimeout(this.transcriptTimeout);
            this.transcriptTimeout = setTimeout(() => {
                this.hideTranscript();
            }, 5000);
        }

        showError(message) {
            this.showTranscript(message);
            this.updateUI('error');
            this.speak(message);
            this.autoHideTranscript();
        }

        async speak(text) {
            if (!text) return;

            const basePath = this.getBasePath();

            // Stop any currently playing audio
            if (this.currentAudio) {
                this.currentAudio.pause();
                this.currentAudio = null;
            }

            try {
                // Use OpenAI TTS for natural Danish voice
                const response = await fetch(basePath + 'api/voice-speak.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ text: text })
                });

                const result = await response.json();

                if (result.success && result.audio) {
                    // Play the audio
                    const audio = new Audio('data:audio/mp3;base64,' + result.audio);
                    this.currentAudio = audio;

                    // When audio finishes, continue conversation if in conversation mode
                    audio.onended = () => {
                        this.currentAudio = null;
                        this.continueConversation();
                    };

                    audio.play().catch(err => {
                        console.warn('[Voice] Audio playback failed:', err);
                        this.currentAudio = null;
                        // Fallback to browser TTS
                        this.speakFallback(text);
                    });
                } else {
                    // Fallback to browser TTS
                    this.speakFallback(text);
                }
            } catch (error) {
                console.warn('[Voice] TTS API error:', error);
                // Fallback to browser TTS
                this.speakFallback(text);
            }
        }

        speakFallback(text) {
            if (!('speechSynthesis' in window)) return;

            speechSynthesis.cancel();

            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = 'da-DK';
            utterance.rate = 1.1;
            utterance.pitch = 1.0;

            // Continue conversation when speech ends
            utterance.onend = () => {
                this.continueConversation();
            };

            speechSynthesis.speak(utterance);
        }
    }

    // Initialize on DOM ready
    function initVoiceAssistant() {
        const assistant = new VoiceAssistant();
        if (assistant.init()) {
            window.voiceAssistant = assistant;
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initVoiceAssistant);
    } else {
        initVoiceAssistant();
    }

})();
