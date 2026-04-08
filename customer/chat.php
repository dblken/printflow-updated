<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

if (!defined('BASE_URL')) define('BASE_URL', '/printflow');

$user_id    = get_user_id();
$user_name  = $_SESSION['user_name'] ?? 'Customer';
$user_avatar = $_SESSION['user_avatar'] ?? '';
$initial_order_id = $_GET['order_id'] ?? null;

$page_title = 'My Messages - PrintFlow';
$use_customer_css = true;
$is_chat_page = true;
$disable_turbo = true;
require_once __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<!-- Load Socket.io and WebRTC Assets -->
<script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
<link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/printflow_call.css">

<style>
    :root {
        --pf-navy: #00151b;
        --pf-navy-card: #00232b;
        --pf-cyan: #53c5e0;
        --pf-cyan-glow: rgba(83,197,224,0.15);
        --pf-border: rgba(83,197,224,0.15);
        --pf-dim: #94a3b8;
        --pf-self-bubble: linear-gradient(135deg,#0a2530,#001a21);
    }

    /* Layout — fill viewport below the site header */
    body.chat-page { overflow: hidden !important; }
    body.chat-page #main-content { padding: 0 !important; min-height: 0 !important; overflow: hidden !important; display: flex; flex-direction: column; }
    body.chat-page #main-header { position: sticky; top: 0; z-index: 100; }

    #chat-root {
        display: grid;
        grid-template-columns: 350px 1fr;
        height: calc(100vh - 65px);
        overflow: hidden;
        background: var(--pf-navy);
        font-family: 'Inter', sans-serif;
    }

    /* ── Sidebar ── */
    .cs-sidebar { display:flex; flex-direction:column; background:rgba(0,35,43,0.97); border-right:1px solid var(--pf-border); overflow:hidden; }
    .cs-sidebar-top { padding:1.25rem 1rem; border-bottom:1px solid var(--pf-border); flex-shrink:0; }
    .cs-sidebar-top h2 { font-size:1.1rem; font-weight:800; color:#fff; margin:0 0 .9rem; }
    .cs-search { position:relative; }
    .cs-search i { position:absolute; left:.75rem; top:50%; transform:translateY(-50%); color:var(--pf-cyan); opacity:.5; }
    .cs-search input { width:100%; box-sizing:border-box; background:rgba(255,255,255,.05); border:1px solid var(--pf-border); border-radius:12px; padding:.55rem .75rem .55rem 2.25rem; font-size:.85rem; color:#fff; outline:none; transition:.2s; }
    .cs-search input:focus { border-color:var(--pf-cyan); background:rgba(255,255,255,.08); }

    .cs-tabs { display:flex; gap:6px; padding:.75rem 1rem; border-bottom:1px solid var(--pf-border); flex-shrink:0; }
    .cs-tab { flex:1; text-align:center; padding:.4rem 0; border-radius:8px; font-size:.75rem; font-weight:700; color:var(--pf-dim); cursor:pointer; background:transparent; border:none; transition:.2s; }
    .cs-tab.active { background:var(--pf-cyan-glow); color:var(--pf-cyan); border:1px solid rgba(83,197,224,.25); }

    .cs-list { flex:1; overflow-y:auto; padding:.5rem; }
    .cs-list::-webkit-scrollbar { width:3px; }
    .cs-list::-webkit-scrollbar-thumb { background:var(--pf-border); border-radius:10px; }

    .conv-card { display:flex; gap:11px; padding:12px 14px; border-radius:14px; margin-bottom:3px; cursor:pointer; border:1px solid transparent; transition:.18s; }
    .conv-card:hover { background:rgba(255,255,255,.03); }
    .conv-card.active { background:var(--pf-cyan-glow); border-color:rgba(83,197,224,.25); }
    .conv-av { width:44px; height:44px; border-radius:11px; background:rgba(83,197,224,.14); border:1px solid var(--pf-border); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:.95rem; color:var(--pf-cyan); flex-shrink:0; overflow:hidden; }
    .conv-av img { width:100%; height:100%; object-fit:cover; }
    .conv-info { flex:1; min-width:0; }
    .conv-top { display:flex; justify-content:space-between; align-items:baseline; gap:4px; }
    .conv-name { font-size:.88rem; font-weight:700; color:#fff; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .conv-time { font-size:.65rem; color:var(--pf-dim); font-weight:700; flex-shrink:0; }
    .conv-sub { font-size:.68rem; color:var(--pf-cyan); font-weight:800; text-transform:uppercase; letter-spacing:.04em; margin-top:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; opacity:.85; }
    .conv-prev { font-size:.75rem; color:var(--pf-dim); margin-top:4px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; opacity:.7; }

    /* ── Main Chat Window ── */
    .cs-window { display:flex; flex-direction:column; overflow:hidden; background:#001a21; position:relative; }
    .cs-header { display:flex; align-items:center; gap:12px; padding:1rem 1.5rem; border-bottom:1px solid var(--pf-border); background:rgba(0,20,26,.45); backdrop-filter:blur(10px); z-index:20; flex-shrink:0; }
    .cs-header-info { flex:1; min-width:0; }
    .cs-header-name { font-size:1rem; font-weight:800; color:#fff; margin:0; display:flex; align-items:center; gap:8px; }
    .cs-header-meta { font-size:.75rem; color:var(--pf-cyan); font-weight:700; opacity:.8; margin:0; }
    
    .cs-h-actions { display: flex; gap: 8px; }
    .cs-h-btn { 
        width: 38px; height: 38px; border-radius: 10px; border: 1px solid var(--pf-border); 
        background: rgba(255,255,255,.05); color: var(--pf-cyan); 
        display: flex; align-items:center; justify-content:center; cursor:pointer; font-size: 1rem; transition:.2s;
    }
    .cs-h-btn:hover { background: rgba(83,197,224,.12); color: #fff; }

    .h-menu-wrap { position:relative; }
    .h-dropdown { display:none; position:absolute; top:calc(100% + 8px); right:0; background:#00232b; border:1px solid var(--pf-border); border-radius:13px; width:170px; z-index:200; overflow:hidden; box-shadow:0 12px 30px rgba(0,0,0,.4); }
    .h-dropdown.show { display:block; }
    .h-drop-item { padding:10px 16px; font-size:.84rem; font-weight:700; color:#fff; cursor:pointer; display:flex; align-items:center; gap:10px; transition:.15s; }
    .h-drop-item:hover { background:rgba(83,197,224,.1); color:var(--pf-cyan); }

    /* Messages Area */
    #messagesArea { flex:1; overflow-y:auto; padding:1.5rem; display:flex; flex-direction:column; gap:4px; background:radial-gradient(circle at top,#00232b 0%,#00151b 100%); scroll-behavior:smooth; }
    #messagesArea::-webkit-scrollbar { width:4px; }
    #messagesArea::-webkit-scrollbar-thumb { background:var(--pf-border); border-radius:10px; }

    /* Bubbles & Grouping */
    .brow { display:flex; width:100%; align-items:flex-end; gap:8px; margin-bottom:12px; position:relative; transition: margin 0.2s; }
    .brow.self { flex-direction:row-reverse; }
    .brow.system { justify-content:center; margin-bottom: 24px; }
    
    .brow.grouped-msg { margin-bottom: 2px !important; }
    .brow.grouped-msg-next .b-meta { display: none !important; }
    .brow.grouped-msg-next .conv-av { visibility: hidden; }

    .b-col { max-width:75%; position:relative; }
    .brow.self .b-col { display:grid; justify-items:end; }
    .brow.other .b-col { display:flex; flex-direction:column; align-items:flex-start; }
    
    .bubble { display:inline-block; padding:10px 16px; border-radius:20px; font-size:.9rem; font-weight:500; line-height:1.45; max-width:100%; word-break:break-word; position: relative; }
    .brow.self .bubble { background: var(--pf-self-bubble); border:1px solid var(--pf-border); border-radius:20px 20px 4px 20px; color: #fff; }
    .brow.other .bubble { background:rgba(255,255,255,.07); border:1px solid var(--pf-border); border-radius:20px 20px 20px 4px; color: #fff; }
    
    .brow.grouped-msg.other .bubble { border-radius: 20px 20px 4px 4px; }
    .brow.grouped-msg-next.other .bubble { border-radius: 4px 20px 20px 4px; }
    .brow.grouped-msg.self .bubble { border-radius: 20px 20px 4px 4px; }
    .brow.grouped-msg-next.self .bubble { border-radius: 20px 4px 4px 20px; }

    .brow.system .bubble { background:rgba(255,255,255,.03); color:var(--pf-dim); font-size:.78rem; border:none; border-radius:10px; padding:4px 12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }

    .b-meta { font-size:.65rem; color:var(--pf-dim); font-weight:700; opacity:.6; margin-top:6px; display:flex; gap:4px; }
    .brow.self .b-meta { justify-content:flex-end; }

    /* Action Bar (Messenger Style) */
    .brow:hover .b-actions, .brow.has-active-menu .b-actions { opacity:1; pointer-events:auto; }
    .b-actions { 
        opacity:0; pointer-events:none; display:flex; align-items: center; gap:4px; 
        position:absolute; top:50%; transform:translateY(-50%); z-index:100; transition:.2s; 
        background: #00232b; border: 1px solid var(--pf-border); 
        border-radius:999px; padding:4px 8px; backdrop-filter:blur(12px); box-shadow:0 4px 20px rgba(0,0,0,0.4); 
    }
    .brow.other .b-actions { left:calc(100% + 12px); }
    .brow.self  .b-actions { right:calc(100% + 12px); flex-direction:row-reverse; }
    
    .ab { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:var(--pf-cyan); cursor:pointer; font-size:1.1rem; transition:.15s; }
    .ab:hover { background: rgba(83,197,224,0.15); color: #fff; }

    /* More Menu Sub-Menu */
    .more-menu { 
        display:none; position:absolute; top:100%; right:0; background:#00232b; 
        border:1px solid var(--pf-border); border-radius:12px; width:160px; z-index:151; 
        overflow:hidden; box-shadow:0 12px 30px rgba(0,0,0,0.5); margin-top: 8px;
    }
    .more-menu.show { display:block; animation: menuFade 0.2s ease; }
    .mi { padding:10px 16px; font-size:.85rem; font-weight:700; color:#fff; cursor:pointer; display:flex; align-items:center; gap:10px; transition:.15s; text-align: left; }
    .mi:hover { background:rgba(83,197,224,0.1); color:var(--pf-cyan); }

    /* Reactions Attached to Bubble */
    .react-display { 
        display:flex; gap:4px; position: absolute; bottom: -10px; z-index: 10;
        background: #00232b; border: 1px solid var(--pf-border); border-radius: 999px; padding: 3px 10px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.3); cursor: default; white-space: nowrap;
    }
    .brow.self .react-display { right: 8px; }
    .brow.other .react-display { left: 8px; }
    .react-chip { font-size:.85rem; display:flex; align-items:center; gap:4px; color: #fff; }
    .react-chip b { font-weight: 800; font-size: 0.75rem; color: var(--pf-cyan); }

    /* Reaction Picker */
    .react-picker { 
        display:none; position:absolute; bottom:calc(100% + 12px); left:50%; transform:translateX(-50%); 
        background:#00232b; border:1px solid var(--pf-border); border-radius:999px; padding:0 18px; 
        gap:10px; z-index:150; box-shadow:0 12px 40px rgba(0,0,0,0.5); height: 50px; align-items: center; justify-content: center;
        animation: pickerPop 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .react-picker.show { display:flex; }
    .react-picker span { font-size:1.6rem; cursor:pointer; transition:.15s; margin: 0 4px; }
    .react-picker span:hover { transform:scale(1.3) translateY(-4px); }

    /* Seen Indicator */
    .seen-wrapper { display:flex; width:100%; margin-top:2px; min-height:16px; align-items:center; }
    .brow.self .seen-wrapper { justify-content: flex-end; }
    .seen-avatar { width: 14px; height: 14px; border-radius: 50%; object-fit: cover; border: 1px solid #fff; }

    /* Reply Sub-Area */
    #replyBox { 
        display:none; background:var(--pf-navy-card); border-top:1px solid var(--pf-border); 
        padding:10px 1.5rem; justify-content:space-between; align-items:center; gap:10px; 
    }
    .reply-wrap { border-left:3px solid var(--pf-cyan); padding-left:12px; overflow:hidden; }
    .reply-head { font-size:.7rem; font-weight:800; color:var(--pf-cyan); text-transform:uppercase; margin-bottom:2px; }
    .reply-preview { font-size:.85rem; color:var(--pf-dim); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:400px; }
    .reply-close { background:transparent; border:none; color:var(--pf-dim); cursor:pointer; font-size:1.2rem; }

    /* ── Window Footer (Compact Staff Layout) ── */
    .cs-footer { padding: 0.75rem 1.25rem; border-top: 1px solid var(--pf-border); background:rgba(0,20,26,.8); backdrop-filter:blur(10px); flex-shrink:0; z-index:20; }
    .chat-input-area { display: flex; align-items: center; gap: 10px; width: 100%; max-width: 900px; margin: 0 auto; }
    
    .mic-btn {
        width: 40px; height: 40px; border-radius: 12px; background: rgba(255,255,255,.05); border: 1px solid var(--pf-border); 
        color: var(--pf-dim); display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1rem; transition: all 0.2s; flex-shrink: 0;
    }
    .mic-btn:hover { background: rgba(83,197,224,0.1); color: var(--pf-cyan); }
    .mic-btn.recording { background: rgba(239, 68, 68, 0.15); border-color: rgba(239,68,68,0.3); color: #ef4444; animation: pulse-rec 2s infinite; }

    .input-bar { 
        flex: 1; display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,.05); border: 2px solid transparent; 
        border-radius: 16px; padding: 4px 4px 4px 12px; transition: all 0.2s; position: relative;
    }
    .input-bar:focus-within { background: rgba(255,255,255,.08); border-color: var(--pf-cyan); }

    .footer-action-btn { 
        width: 38px; height: 38px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
        color: var(--pf-dim); cursor: pointer; transition: all 0.15s; background: transparent; flex-shrink: 0;
    }
    .footer-action-btn:hover { color: var(--pf-cyan); background: rgba(83,197,224,0.1); }

    #customerMsgInput { 
        flex: 1; background: transparent; border: none !important; outline: none !important; color: #fff; 
        font-size: 0.95rem; font-weight: 500; padding: 10px 0; font-family: inherit; line-height: 1.4;
    }
    #customerMsgInput::placeholder { color: rgba(159, 196, 212, 0.4); }

    .char-counter { font-size: 10px; font-weight: 800; color: var(--pf-dim); opacity: 0.5; white-space: nowrap; align-self: center; }

    .btn-send { 
        background: var(--pf-cyan); color: #00151b; border: none; width: 44px; height: 44px; border-radius: 14px; 
        display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; flex-shrink: 0;
        box-shadow: 0 2px 10px rgba(83, 197, 224, 0.2);
    }
    .btn-send:hover { transform: scale(1.05); filter: brightness(1.1); }

    /* Shared Media & Modals */
    #galleryPanel { position:absolute; right:0; top:0; bottom:0; width:320px; background:var(--pf-navy-card); border-left:1px solid var(--pf-border); transform:translateX(100%); transition:.3s; z-index:1000; display:flex; flex-direction:column; box-shadow:-10px 0 30px rgba(0,0,0,.3); }
    #galleryPanel.show { transform:translateX(0); }
    .gal-head { padding:1.25rem 1.5rem; border-bottom:1px solid var(--pf-border); display:flex; justify-content:space-between; align-items:center; }
    .gal-grid { flex:1; overflow-y:auto; padding:12px; display:grid; grid-template-columns:repeat(3,1fr); gap:6px; align-content:start; }
    .gal-item { aspect-ratio:1; border-radius:10px; overflow:hidden; cursor:pointer; background:rgba(255,255,255,.04); border:1px solid var(--pf-border); }
    .gal-item img { width:100%; height:100%; object-fit:cover; transition: .2s; }
    .gal-item:hover img { transform: scale(1.1); }

    @keyframes pulse-rec { 0%{box-shadow:0 0 0 0 rgba(239,68,68,.4)} 70%{box-shadow:0 0 0 10px rgba(239,68,68,0)} 100%{box-shadow:0 0 0 0 rgba(239,68,68,0)} }
    @keyframes pickerPop { from { opacity: 0; transform: translateX(-50%) scale(0.8) translateY(10px); } to { opacity: 1; transform: translateX(-50%) scale(1) translateY(0); } }
    @keyframes menuFade { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

    /* Forward Modal CSS */
    #fwdModal { display:none; position:fixed; inset:0; background:transparent; z-index:2000; align-items:center; justify-content:center; }
    #fwdModal.show { display:flex; }
    .fwd-panel { background:rgba(0,35,43,0.7); backdrop-filter:blur(20px); border:1px solid rgba(83,197,224,0.3); border-radius:32px; width:100%; max-width:420px; padding:1.5rem; box-shadow:0 40px 100px rgba(0,0,0,0.5); }
    .fwd-list-item { display:flex; align-items:center; gap:12px; padding:12px; border-radius:16px; transition:.15s; cursor:pointer; border:1.1px solid transparent; }
    .fwd-list-item:hover { background:rgba(255,255,255,.03); }
    .fwd-list-item.selected { background:var(--pf-cyan-glow); border-color:rgba(83,197,224,.3); }
    .fwd-check { width:18px; height:18px; border-radius:50%; border:2px solid var(--pf-border); display:flex; align-items:center; justify-content:center; transition:.2s; }
    .selected .fwd-check { background:var(--pf-cyan); border-color:var(--pf-cyan); }
</style>

<div id="chat-root">
    <!-- ══ Sidebar ══ -->
    <aside class="cs-sidebar">
        <div class="cs-sidebar-top">
            <h2>My Messages</h2>
            <div class="cs-search"><i class="bi bi-search"></i><input type="text" id="convSearch" placeholder="Search orders…" oninput="loadConvs()"></div>
        </div>
        <div class="cs-tabs"><button class="cs-tab active" id="tabActive" onclick="switchTab(false)">Active</button><button class="cs-tab" id="tabArchived" onclick="switchTab(true)">Archived</button></div>
        <div class="cs-list" id="convList"></div>
    </aside>

    <!-- ══ Chat Window ── -->
    <section class="cs-window">
        <div id="welcome" class="flex-1 flex items-center justify-center text-left p-12">
        <div>
            <div class="text-5xl opacity-20 text-white mb-6"><i class="bi bi-chat-heart-fill"></i></div>
            <h3 class="text-3xl font-black text-white letter-spacing-tight">Get in Touch</h3>
            <p class="text-white opacity-50 max-w-xs mt-3 font-bold text-lg leading-snug">Please select an order to start chatting. You can contact our admin or staff directly if you encounter any issues.</p>
        </div>
    </div>
        
        <div id="chatInterface" style="display:none;flex:1;flex-direction:column;overflow:hidden;">
            <header class="cs-header">
                <div id="hAvatar" class="conv-av"></div>
                <div class="cs-header-info"><h3 class="cs-header-name"><span id="hName">...</span><span id="hOnline" style="width:10px;height:10px;background:#22c55e;border-radius:50%;display:none;margin-left:8px;"></span></h3><p class="cs-header-meta" id="hMeta">...</p></div>
                <div class="cs-h-actions">
                    <button class="cs-h-btn" onclick="initiateCall('voice')"><i class="bi bi-telephone-fill"></i></button>
                    <button class="cs-h-btn" onclick="initiateCall('video')"><i class="bi bi-camera-video-fill"></i></button>
                    <div class="h-menu-wrap">
                        <button class="cs-h-btn" onclick="toggleHMenu(event)"><i class="bi bi-three-dots-vertical"></i></button>
                        <div class="h-dropdown" id="hDropdown">
                            <div class="h-drop-item" onclick="openGallery()"><i class="bi bi-images"></i> Shared Media</div>
                            <div class="h-drop-item" id="archItem" onclick="toggleArchive()"><i class="bi bi-archive"></i> Archive</div>
                            <div class="h-drop-item" onclick="openOrderDetails(activeId)"><i class="bi bi-info-circle"></i> Order Details</div>
                        </div>
                    </div>
                </div>
            </header>

            <div id="pinnedBar" onclick="showPinnedModal()" style="display:none; background:var(--pf-navy-card); border-bottom:1px solid var(--pf-border); padding:10px 1.5rem; align-items:center; justify-content:space-between; cursor:pointer;">
                <div style="display:flex;align-items:center;gap:8px;"><i class="bi bi-pin-angle-fill" style="color:var(--pf-cyan);"></i><span id="pinnedTxt" style="font-size:0.75rem; font-weight:800; color:#fff;">0 pinned messages</span></div>
                <i class="bi bi-chevron-right" style="color:var(--pf-dim);font-size:.85rem;"></i>
            </div>

            <div id="messagesArea"></div>

            <div id="galleryPanel">
                <div class="gal-head"><span style="font-weight:800;font-size:1.1rem;color:#fff;">Shared Media</span><button onclick="closeGallery()" style="background:transparent;border:none;color:var(--pf-dim);font-size:1.5rem;cursor:pointer;"><i class="bi bi-x"></i></button></div>
                <div class="gal-grid" id="galleryGrid"></div>
            </div>

            <div id="replyBox">
                <div class="reply-wrap">
                    <div class="reply-head" id="replyHead">Replying to message</div>
                    <div class="reply-preview" id="replyPreviewTxt">...</div>
                </div>
                <button class="reply-close" onclick="cancelReply()"><i class="bi bi-x-circle-fill"></i></button>
            </div>

            <footer class="cs-footer">
                <div class="chat-input-area">
                    <button class="mic-btn" id="customerMicBtn" onclick="toggleRecording()">
                        <i class="bi bi-mic"></i>
                    </button>
                    
                    <div class="input-bar" id="customerInputContainer">
                        <label class="footer-action-btn" title="Send Image or Video">
                            <input type="file" id="customerMediaInput" multiple style="display:none;" onchange="onImgSelected()">
                            <i class="bi bi-image"></i>
                        </label>
                        <input type="text" id="customerMsgInput" placeholder="Type a message..." maxlength="500" style="flex: 1; background: transparent; border: none; outline: none; color: #fff; font-size: 0.95rem; font-weight: 500; padding: 10px 0; font-family: inherit;">
                        <span id="customerCharCount" class="char-counter">0/500</span>
                    </div>

                    <button id="customerSendBtn" class="btn-send" onclick="sendMsg()">
                        <i class="bi bi-send"></i>
                    </button>
                </div>
                <div id="customerImgPreview" style="display:none;margin-top:0.6rem;gap:10px;flex-wrap:wrap;justify-content:center;padding:5px;"></div>
            </footer>
        </div>
    </section>
</div>

    <div id="fwdModal">
        <div class="fwd-panel">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-white font-black text-xl">Forward Message</h3>
                <button onclick="closeFwd()" class="text-white opacity-40 hover:opacity-100 transition-all"><i class="bi bi-x-lg"></i></button>
            </div>
            <div id="fwdPreview" class="p-4 bg-black bg-opacity-20 rounded-xl mb-6 text-white text-sm border-l-4 border-cyan-400 opacity-60 italic"></div>
            <div id="fwdList" class="space-y-2 max-h-60 overflow-y-auto pr-2 mb-6"></div>
            <button id="fwdSendBtn" onclick="doForward()" disabled class="w-full py-4 bg-cyan-500 rounded-2xl text-black font-black text-lg transition-all hover:bg-cyan-400 disabled:opacity-30">Send to 0</button>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div id="detailsModal" style="display:none; position:fixed; inset:0; background:transparent; z-index:3000; align-items:center; justify-content:center;">
        <div style="background:rgba(0,35,43,0.7); backdrop-filter:blur(20px); border:1px solid rgba(83,197,224,0.3); border-radius:32px; width:100%; max-width:600px; max-height:80vh; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 40px 100px rgba(0,0,0,0.5);">
            <div style="padding:1.5rem; border-bottom:1px solid var(--pf-border); display:flex; justify-content:space-between; align-items:center;">
                <div>
                   <h2 style="color:#fff; font-size:1.4rem; font-weight:900; margin:0;">Order Specifications</h2>
                   <p style="color:var(--pf-dim); font-size:0.7rem; font-weight:800; text-transform:uppercase; margin-top:2px;">Production Metadata</p>
                </div>
                <button onclick="document.getElementById('detailsModal').style.display='none'" style="background:transparent; border:none; color:#fff; cursor:pointer; font-size:1.5rem;"><i class="bi bi-x-lg"></i></button>
            </div>
            <div id="detailsBody" style="flex:1; overflow-y:auto; padding:1.5rem;"></div>
        </div>
    </div>

<script src="<?= BASE_URL ?>/public/assets/js/printflow_call.js"></script>
<script>
const BASE = '<?= BASE_URL ?>';
const ME_ID = <?= (int)$user_id ?>;
const ME_NAME = '<?= addslashes($user_name) ?>';
const EMOJIS = {like:'👍', love:'❤️', haha:'😂', wow:'😮', sad:'😢', angry:'😡'};

let activeId = null, lastId = 0, pollTimer = null;
let isArchView = false, isConvArch = false, uploads = [], pfc = null;
let partnerAvatarUrl = '', replyId = null;

async function api(url, method = 'GET', body = null) {
    try {
        const opts = { method };
        if (body) opts.body = (body instanceof FormData) ? body : JSON.stringify(body);
        const r = await fetch(BASE + url, opts);
        return await r.json();
    } catch(e) { return { success: false, error: e.message }; }
}

function switchTab(archived) {
    isArchView = archived;
    document.getElementById('tabActive').classList.toggle('active', !archived);
    document.getElementById('tabArchived').classList.toggle('active', archived);
    loadConvs();
}

function loadConvs() {
    const q = document.getElementById('convSearch').value;
    api(`/public/api/chat/list_conversations.php?archived=${isArchView?1:0}`).then(res => {
        const list = document.getElementById('convList');
        if (!res.conversations || !res.conversations.length) {
            list.innerHTML = `
            <div class="p-12 text-center">
                <div class="text-5xl opacity-10 text-white mb-4"><i class="bi bi-patch-question-fill"></i></div>
                <div class="text-white opacity-40 font-bold text-sm">No ${isArchView?'archived':'active'} orders found.</div>
            </div>`;
            return;
        }
        list.innerHTML = res.conversations.map(c => {
            const name = c.staff_name || 'PrintFlow Team';
            const active = activeId === c.order_id ? 'active' : '';
            return `
            <div class="conv-card ${active}" onclick="openChat(${c.order_id},'${esc(name)}','${esc(c.service_name||'Order')}',${c.is_archived?1:0},'${esc(c.staff_avatar||'')}')">
                <div class="conv-av">${c.staff_avatar ? `<img src="${BASE}/${c.staff_avatar}">` : `<span>${name[0].toUpperCase()}</span>`}</div>
                <div class="conv-info">
                    <div class="conv-top"><span class="conv-name">${esc(name)}</span><span class="conv-time">${fmtTimeAgo(c.last_message_at)}</span></div>
                    <div class="conv-sub">ORDER #${c.order_id} · ${esc(c.service_name||'Order')}</div>
                    <div class="conv-prev">${esc(c.last_message||'No messages yet')}</div>
                </div>
            </div>`;
        }).join('');
    });
}

function openChat(id, name, meta, archived, avatar = '') {
    activeId = id; lastId = 0; isConvArch = !!archived; partnerAvatarUrl = avatar ? `${BASE}/${avatar}` : '';
    document.getElementById('welcome').style.display = 'none';
    document.getElementById('chatInterface').style.display = 'flex';
    document.getElementById('hName').textContent = name;
    document.getElementById('hMeta').textContent = 'Order #' + id + ' · ' + meta;
    const hAv = document.getElementById('hAvatar');
    hAv.innerHTML = avatar ? `<img src="${BASE}/${avatar}" style="width:100%;height:100%;object-fit:cover;">` : `<span>${name[0].toUpperCase()}</span>`;
    updateArchUI(archived);
    document.getElementById('messagesArea').innerHTML = '';
    loadMsgs();
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(loadMsgs, 3000);
}

function updateArchUI(arch) {
    isConvArch = !!arch;
    document.getElementById('archItem').innerHTML = arch ? '<i class="bi bi-arrow-up-circle"></i> Unarchive' : '<i class="bi bi-archive"></i> Archive';
}

function loadMsgs() {
    if (!activeId) return;
    const box = document.getElementById('messagesArea');
    api(`/public/api/chat/fetch_messages.php?order_id=${activeId}&last_id=${lastId}&is_active=1`).then(res => {
        if (!res.success) return;
        if (lastId === 0) box.innerHTML = '';
        
        const rxMap = {};
        (res.reactions || []).forEach(r => { if (!rxMap[r.message_id]) rxMap[r.message_id] = []; rxMap[r.message_id].push(r); });

        res.messages.forEach(m => {
            appendMsgUI(m);
            lastId = Math.max(lastId, m.id);
        });

        Object.keys(rxMap).forEach(mid => renderReactions(mid, rxMap[mid]));
        document.getElementById('hOnline').style.display = res.partner.is_online ? 'inline-block' : 'none';
        updatePinnedBar(res.pinned_messages || []);
        if (res.last_seen_message_id) updateSeenIndicator(res.last_seen_message_id);
        if (res.messages.length) box.scrollTo({top: box.scrollHeight, behavior: 'smooth'});
    });
}

function appendMsgUI(m) {
    const box = document.getElementById('messagesArea');
    if (document.getElementById(`ms-${m.id}`)) return;

    // Messenger Grouping Logic
    const prevRow = box.lastElementChild;
    const isGrouped = prevRow && !m.is_system && 
                      prevRow.getAttribute('data-sender') === (m.is_self ? 'self' : m.sender) && 
                      prevRow.getAttribute('data-time') === m.created_at;

    const row = document.createElement('div');
    row.id = `ms-${m.id}`;
    row.className = `brow ${m.is_system ? 'system' : (m.is_self ? 'self' : 'other')}`;
    row.setAttribute('data-sender', m.is_self ? 'self' : m.sender);
    row.setAttribute('data-time', m.created_at);

    if (isGrouped) {
        prevRow.classList.add('grouped-msg');
        row.classList.add('grouped-msg-next');
    }

    if (m.is_system) {
        row.innerHTML = `<div class="b-col"><div class="bubble">${esc(m.message)}</div></div>`;
        box.appendChild(row); return;
    }

    const msgB64 = btoa(unescape(encodeURIComponent(m.message || '')));
    const avHtml = (!m.is_self) ? `<div class="conv-av" style="width:32px; height:32px; border-radius:50%; align-self:flex-end;">${m.sender_avatar ? `<img src="${BASE}/${m.sender_avatar}" style="border-radius:50%;">` : `<span>${(m.sender_name||'S')[0].toUpperCase()}</span>`}</div>` : '';
    
    row.innerHTML = `
        ${avHtml}
        <div class="b-col">
            <div class="b-actions">
                <div class="ab" onclick="toggleReact(${m.id},event)" style="position:relative;"><i class="bi bi-emoji-smile"></i><div class="react-picker" id="rp-${m.id}">${Object.entries(EMOJIS).map(([k,v])=>`<span onclick="react(${m.id},'${k}')">${v}</span>`).join('')}</div></div>
                <div class="ab" onclick="initReply(${m.id},'${msgB64}')"><i class="bi bi-reply-fill"></i></div>
                <div class="ab" style="position:relative;" onclick="toggleMore(${m.id},event)"><i class="bi bi-three-dots"></i><div class="more-menu" id="mm-${m.id}"><div class="mi" onclick="pinMsg(${m.id})"><i class="bi bi-pin-angle"></i> Pin</div><div class="mi" onclick="initFwd(${m.id},'${msgB64}')"><i class="bi bi-arrow-right"></i> Forward</div></div></div>
            </div>
            <div class="bubble">
                ${m.image_path ? `<img class="chat-img" src="${m.image_path}" onclick="window.open(this.src)">` : ''}
                ${m.message ? `<span>${esc(m.message)}</span>` : ''}
                <div class="react-display" id="rd-${m.id}" style="display:none;"></div>
            </div>
            <div class="b-meta">${fmtShort(m.created_at)}</div>
            ${m.is_self ? `<div class="seen-wrapper" id="sw-${m.id}"></div>` : ''}
        </div>`;
    box.appendChild(row);
}

function initReply(id, msgB64) {
    replyId = id;
    const txt = decodeURIComponent(escape(atob(msgB64)));
    document.getElementById('replyBox').style.display = 'flex';
    document.getElementById('replyPreviewTxt').textContent = txt || 'Attachment';
    document.getElementById('customerMsgInput').focus();
    closeAllMenus();
}

function cancelReply() {
    replyId = null;
    document.getElementById('replyBox').style.display = 'none';
}

function updateSeenIndicator(lastSeenId) {
    document.querySelectorAll('.seen-wrapper').forEach(el => el.innerHTML = '');
    const selfRows = [...document.querySelectorAll('.brow.self')];
    let lastSeenRow = null;
    selfRows.forEach(row => {
        const id = parseInt(row.id.replace('ms-', ''));
        if (id <= lastSeenId) lastSeenRow = row;
    });
    if (lastSeenRow) {
        const wrap = lastSeenRow.querySelector('.seen-wrapper');
        if (wrap) wrap.innerHTML = partnerAvatarUrl ? `<img src="${partnerAvatarUrl}" class="seen-avatar" title="Seen">` : `<span style="font-size:10px; color:var(--pf-dim); font-weight:800;">✓ Seen</span>`;
    }
}

function renderReactions(id, rx) {
    const el = document.getElementById('rd-' + id); if (!el) return;
    if (!rx || !rx.length) { el.style.display = 'none'; return; }
    const counts = {}; rx.forEach(r => counts[r.reaction_type] = (counts[r.reaction_type]||0)+1);
    el.innerHTML = Object.entries(counts).map(([t, c]) => `<div class="react-chip">${EMOJIS[t]||t}${c>1?` <b>${c}</b>`:''}</div>`).join('');
    el.style.display = 'flex';
}

function updatePinnedBar(pins) {
    const bar = document.getElementById('pinnedBar');
    if (!pins || !pins.length) { bar.style.display = 'none'; return; }
    bar.style.display = 'flex';
    document.getElementById('pinnedTxt').textContent = pins.length + ' pinned message' + (pins.length>1?'s':'');
}

function sendMsg() {
    const input = document.getElementById('customerMsgInput'), txt = input.value.trim();
    if (!txt && !uploads.length) return;
    const btn = document.getElementById('customerSendBtn');
    btn.disabled = true;
    const fd = new FormData(); fd.append('order_id', activeId);
    if (txt) fd.append('message', txt);
    if (replyId) fd.append('reply_id', replyId);
    uploads.forEach(f => fd.append('image[]', f));
    api('/public/api/chat/send_message.php', 'POST', fd).then(res => {
        if (res.success) { 
            input.value = ''; uploads = []; 
            document.getElementById('customerImgPreview').style.display='none'; 
            cancelReply();
            loadMsgs(); 
        }
        btn.disabled = false;
        document.getElementById('customerCharCount').textContent = '0/500';
    });
}

function onImgSelected() {
    const input = document.getElementById('customerMediaInput');
    for (const f of input.files) uploads.push(f);
    const prev = document.getElementById('customerImgPreview');
    prev.style.display = 'flex';
    prev.innerHTML = uploads.map((f,i) => `<div style="position:relative;"><img src="${URL.createObjectURL(f)}" style="width:50px;height:50px;border-radius:10px;object-fit:cover;"><button onclick="uploads.splice(${i},1);onImgSelected()" style="position:absolute;top:-5px;right:-5px;width:18px;height:18px;border-radius:50%;background:red;color:#fff;border:none;font-size:10px;cursor:pointer;">×</button></div>`).join('');
    input.value = '';
}

function toggleReact(id, e) { 
    e.stopPropagation(); 
    const el = document.getElementById('rp-'+id); 
    const cur = el.classList.contains('show'); 
    closeAllMenus(); 
    if(!cur) {
        el.classList.add('show');
        // Smart position: check if it overflows bottom
        const rect = el.getBoundingClientRect();
        if (rect.bottom > window.innerHeight) {
            el.style.bottom = '100%';
            el.style.top = 'auto';
            el.style.marginBottom = '12px';
        } else {
            el.style.bottom = 'auto';
            el.style.top = 'calc(100% + 12px)';
            el.style.marginTop = '0';
        }
    }
}
function react(id, type) { const fd = new FormData(); fd.append('message_id',id); fd.append('reaction_type',type); api('/public/api/chat/react_message.php','POST',fd).then(r=>loadMsgs()); closeAllMenus(); }

function toggleMore(id, e) { 
    e.stopPropagation(); 
    const el = document.getElementById('mm-'+id); 
    const cur = el.classList.contains('show'); 
    closeAllMenus(); 
    if(!cur) {
        el.classList.add('show');
        const rect = el.getBoundingClientRect();
        if (rect.bottom > window.innerHeight) {
            el.style.bottom = 'calc(100% + 8px)';
            el.style.top = 'auto';
        } else {
            el.style.bottom = 'auto';
            el.style.top = '100%';
        }
    }
}
function pinMsg(id) { const fd = new FormData(); fd.append('message_id',id); api('/public/api/chat/pin_message.php','POST',fd).then(r=>loadMsgs()); closeAllMenus(); }

let fwdMsgData = null, selectedFwd = [];
function initFwd(id, msgB64) {
    fwdMsgData = { id, text: decodeURIComponent(escape(atob(msgB64))) };
    selectedFwd = [];
    document.getElementById('fwdModal').classList.add('show');
    document.getElementById('fwdPreview').textContent = fwdMsgData.text || '📸 Attachment';
    document.getElementById('fwdSendBtn').disabled = true;
    document.getElementById('fwdSendBtn').textContent = 'Send to 0';
    loadFwdList();
    closeAllMenus();
}
function closeFwd() { document.getElementById('fwdModal').classList.remove('show'); }
function openOrderDetails(id) {
    if (!id) return;
    const modal = document.getElementById('detailsModal');
    const body = document.getElementById('detailsBody');
    body.innerHTML = '<div class="text-center p-8 text-white opacity-50"><i class="bi bi-hourglass-split animate-spin text-2xl"></i><p class="mt-2">Fetching specs...</p></div>';
    modal.style.display = 'flex';
    
    api(`/public/api/chat/order_details.php?order_id=${id}`).then(res => {
        if (!res.success) { body.innerHTML = `<p class='text-red-400 p-4'>${res.error || 'Failed to load details'}</p>`; return; }
        const { order, items } = res;
        
        let itemsHtml = items.map(it => {
            const specs = it.customization || {};
            // Filter out empty or unwanted fields
            const entries = Object.entries(specs).filter(([k,v]) => v && v !== 'null' && typeof v !== 'object' && k !== 'service_type' && k !== 'branch_id');
            
            return `
            <div style="background:rgba(255,255,255,0.03); border:1px solid var(--pf-border); border-radius:20px; padding:1.25rem; margin-bottom:1rem;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1rem;">
                    <div>
                        <div style="font-size:0.75rem; color:var(--pf-cyan); font-weight:800; text-transform:uppercase; margin-bottom:2px;">${it.category || 'Service Item'}</div>
                        <div style="font-size:1.1rem; font-weight:900; color:#fff;">${it.service_name}</div>
                    </div>
                    <div style="background:var(--pf-cyan); color:#00151b; font-size:0.7rem; font-weight:900; padding:4px 10px; border-radius:10px;">QTY: ${it.quantity}</div>
                </div>
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; font-size:0.85rem;">
                    ${entries.map(([k,v]) => `
                        <div>
                            <div style="font-size:0.65rem; color:var(--pf-dim); font-weight:800; text-transform:uppercase;">${k.replace(/_/g,' ')}</div>
                            <div style="font-weight:700; color:#fff;">${v}</div>
                        </div>
                    `).join('')}
                </div>
            </div>`;
        }).join('');

        body.innerHTML = `
            <div style="margin-bottom:1.5rem; display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                <div style="background:var(--pf-cyan-glow); padding:1rem; border-radius:16px; border:1px solid rgba(83,197,224,0.2);">
                    <div style="font-size:0.65rem; font-weight:800; color:var(--pf-cyan); text-transform:uppercase; margin-bottom:4px;">Main Status</div>
                    <div style="font-size:1rem; font-weight:900; color:#fff;">${order.status}</div>
                </div>
                <div style="background:rgba(255,165,0,0.05); padding:1rem; border-radius:16px; border:1px solid rgba(255,165,0,0.2);">
                    <div style="font-size:0.65rem; font-weight:800; color:var(--pf-orange); text-transform:uppercase; margin-bottom:4px;">Order Date</div>
                    <div style="font-size:1rem; font-weight:900; color:#fff;">${order.order_date}</div>
                </div>
            </div>
            ${itemsHtml || '<div class="text-center p-8 opacity-40 italic">No specific items found for this order.</div>'}
        `;
    });
    closeAllMenus();
}
function loadFwdList() {
    api('/public/api/chat/list_conversations.php?archived=0').then(res => {
        const list = document.getElementById('fwdList');
        if (!res.conversations) return;
        list.innerHTML = res.conversations.map(c => {
            const isSel = selectedFwd.includes(c.order_id);
            return `
            <div class="fwd-list-item ${isSel?'selected':''}" onclick="toggleFwdTarget(${c.order_id})">
                <div class="conv-av" style="width:36px;height:36px;">${c.staff_avatar ? `<img src="${BASE}/${c.staff_avatar}">` : `<span>${(c.staff_name||'S')[0].toUpperCase()}</span>`}</div>
                <div style="flex:1;"><div style="font-size:.85rem;font-weight:700;color:#fff;">${esc(c.staff_name)}</div><div style="font-size:.7rem;color:var(--pf-dim);">Order #${c.order_id}</div></div>
                <div class="fwd-check">${isSel?'<i class="bi bi-check text-xs"></i>':''}</div>
            </div>`;
        }).join('');
    });
}
function toggleFwdTarget(id) {
    const idx = selectedFwd.indexOf(id);
    if (idx === -1) selectedFwd.push(id); else selectedFwd.splice(idx,1);
    document.getElementById('fwdSendBtn').disabled = selectedFwd.length === 0;
    loadFwdList();
}
async function doForward() {
    if (!fwdMsgData || !selectedFwd.length) return;
    const btn = document.getElementById('fwdSendBtn');
    btn.disabled = true; btn.textContent = 'Sending...';
    let count = 0;
    for (const tid of selectedFwd) {
        const fd = new FormData();
        fd.append('order_id', tid);
        fd.append('message', (fwdMsgData.text ? `[Forwarded]: ${fwdMsgData.text}` : '[Forwarded Attachment]'));
        const r = await api('/public/api/chat/send_message.php', 'POST', fd);
        if (r.success) count++;
    }
    closeFwd(); loadConvs();
}

function toggleHMenu(e) { e.stopPropagation(); document.getElementById('hDropdown').classList.toggle('show'); }
function closeAllMenus() { document.querySelectorAll('.react-picker,.more-menu,.h-dropdown').forEach(el=>el.classList.remove('show')); }
window.addEventListener('click', closeAllMenus);

function initiateCall(type) {
    if (!pfc) pfc = new PrintFlowCall({ 
        userId: ME_ID, 
        userName: ME_NAME, 
        role: 'Customer',
        userAvatar: partnerAvatarUrl // Actually should be customer avatar, but placeholder for now
    });
    const fd = new FormData(); fd.append('order_id', activeId);
    api('/public/api/chat/status.php','POST',fd).then(res => {
        if (res.partner) pfc.startCall(res.partner.id, 'Staff', type, activeId, res.partner.name, res.partner.avatar ? BASE+'/'+res.partner.avatar : '');
    });
}

function openGallery() {
    if (!activeId) return;
    const gallery = document.getElementById('galleryPanel');
    const grid = document.getElementById('galleryGrid');
    grid.innerHTML = '<div style="grid-column: span 3; padding:3rem; text-align:center; color:rgba(255,255,255,0.2);"><i class="bi bi-hourglass-split animate-spin text-2xl"></i></div>';
    gallery.classList.add('show');
    
    api(`/public/api/chat/fetch_media.php?order_id=${activeId}`).then(res => {
        if (!res.media || !res.media.length) {
            grid.innerHTML = `
            <div style="grid-column: span 3; padding:5rem 1rem; text-align:center; color:rgba(255,255,255,0.2);">
                <i class="bi bi-images" style="font-size:3rem; display:block; margin-bottom:1rem; opacity:0.5;"></i>
                <div style="font-size:0.9rem; font-weight:700;">No shared media yet.</div>
                <div style="font-size:0.75rem; margin-top:4px;">Photos and videos sent in this chat will appear here.</div>
            </div>`;
            return;
        }
        grid.innerHTML = res.media.map(m => `<div class="gal-item" onclick="window.open('${m.message_file||m.image_path}')"><img src="${m.message_file||m.image_path}"></div>`).join('');
    });
    closeAllMenus();
}
function closeGallery() { document.getElementById('galleryPanel').classList.remove('show'); }
function toggleArchive() { const fd = new FormData(); fd.append('order_id',activeId); fd.append('archive',isConvArch?0:1); api('/public/api/chat/set_archived.php','POST',fd).then(res=>{ if(res.success) { isConvArch = !isConvArch; updateArchUI(isConvArch); loadConvs(); document.getElementById('hDropdown').classList.remove('show'); }}); }

function esc(s) { if(!s) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function fmtTimeAgo(d) { if(!d) return ''; const t=new Date(d.replace(/-/g,'/')), diff=(Date.now()-t)/1000; if(diff<60) return 'now'; if(diff<3600) return Math.floor(diff/60)+'m'; if(diff<86400) return Math.floor(diff/3600)+'h'; return Math.floor(diff/86400)+'d'; }
function fmtShort(d) { if(!d) return ''; if(typeof d==='string' && (d.includes('AM')||d.includes('PM'))) return d; return new Date(d.replace(/-/g,'/')).toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'}); }

document.getElementById('customerMsgInput').addEventListener('input', function() { 
    document.getElementById('customerCharCount').textContent = this.value.length + '/500';
});
document.getElementById('customerMsgInput').addEventListener('keydown', e => { if(e.key==='Enter' && !e.shiftKey) { e.preventDefault(); sendMsg(); } });

loadConvs();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
