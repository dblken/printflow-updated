<?php
/**
 * Customer Chat - Premium Two-panel Glassmorphism UI (Fixed)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
if (!defined('BASE_URL')) define('BASE_URL', '/printflow');

require_role('Customer');

$order_id = (int)($_GET['order_id'] ?? 0);
$customer_id = get_user_id();

// Mark notification as read
if (isset($_GET['mark_read']) && $order_id) {
    $notification_id = (int)$_GET['mark_read'];
    db_execute("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND customer_id = ?", 'ii', [$notification_id, $customer_id]);
    redirect(BASE_URL . '/customer/chat.php?order_id=' . $order_id);
}

if ($order_id) {
    $order = db_query("SELECT o.order_id, o.status, o.customer_id FROM orders o WHERE o.order_id = ? AND o.customer_id = ?", 'ii', [$order_id, $customer_id]);
    if (empty($order)) redirect(BASE_URL . '/customer/messages.php');
}

$page_title = $order_id ? "Chat - Order #{$order_id} - PrintFlow" : 'Messages - PrintFlow';
$use_customer_css = true;
$is_chat_page = true;
$disable_turbo = true;
require_once __DIR__ . '/../includes/header.php';
?>
<!-- Load Bootstrap Icons for Chat UI -->
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/bootstrap-icons.min.css">


<!-- Load Socket.io and WebRTC Call Assets -->
<script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
<link rel="stylesheet" href="/printflow/public/assets/css/printflow_call.css">
<script src="/printflow/public/assets/js/printflow_call.js"></script>

<style>
/* ====================================================
   Customer Chat — Full-Page Premium Dark Glass Theme
   PrintFlow · Navy-Cyan Design System
   ==================================================== */

/* Reset page chrome for full-height chat */
body.chat-page {
    overflow-x: hidden;
    overflow-y: auto;
}

body.chat-page::before {
    display: none !important;
}
body.chat-page main#main-content {
    padding: 0 !important;
    background: transparent !important;
    overflow: visible !important;
}
#chat-outer {
    width: 100%;
    height: calc(100vh - 64px) !important; /* subtract header */
    display: flex;
    overflow: hidden;
    background: #00151b;
    position: relative;
    z-index: 1;
}

/* --- Two-Panel Shell --- */
.glass-shell {
    display: flex;
    width: 100%;
    height: 100%;
    overflow: hidden;
}

/* ===== SIDEBAR ===== */
.chat-sidebar {
    width: 320px;
    min-width: 280px;
    max-width: 360px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    background: rgba(0, 35, 43, 0.95);
    border-right: 1px solid rgba(83, 197, 224, 0.12);
    overflow: hidden;
    transition: transform 0.3s ease;
}
.sidebar-header {
    padding: 1.25rem 1.25rem 0.75rem;
    border-bottom: 1px solid rgba(83,197,224,0.1);
    flex-shrink: 0;
}
.sidebar-title {
    font-size: 1.1rem;
    font-weight: 800;
    color: #eaf6fb;
    margin: 0 0 0.875rem 0;
    letter-spacing: -0.01em;
}
.search-container {
    position: relative;
    display: flex;
    align-items: center;
}
.search-icon {
    position: absolute;
    left: 0.7rem;
    width: 16px;
    height: 16px;
    color: #53C5E0;
    opacity: 0.6;
    pointer-events: none;
}
.search-container input {
    width: 100%;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(83,197,224,0.2);
    border-radius: 10px;
    padding: 0.55rem 0.875rem 0.55rem 2.25rem;
    color: #e0f2fe;
    font-size: 0.85rem;
    outline: none;
    transition: border-color 0.2s;
}
.search-container input::placeholder { color: #4a7a8a; }
.search-container input:focus { border-color: rgba(83,197,224,0.5); }

/* Tabs */
.conv-tabs {
    display: flex;
    border-bottom: 1px solid rgba(83,197,224,0.1);
    flex-shrink: 0;
}
.conv-tab {
    flex: 1;
    text-align: center;
    padding: 0.65rem;
    font-size: 0.8rem;
    font-weight: 700;
    color: #4a7a8a;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.conv-tab.active {
    color: #53C5E0;
    border-bottom-color: #53C5E0;
}
.conv-tab:hover:not(.active) { color: #8bbdcc; }

/* Conversation List */
#convList {
    flex: 1;
    overflow-y: auto;
    padding: 0.5rem 0;
}
#convList::-webkit-scrollbar { width: 4px; }
#convList::-webkit-scrollbar-track { background: transparent; }
#convList::-webkit-scrollbar-thumb { background: rgba(83,197,224,0.2); border-radius: 4px; }

.chat-item {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    padding: 0.875rem 1.25rem;
    cursor: pointer;
    border-left: 3px solid transparent;
    transition: all 0.18s;
}
.chat-item:hover { background: rgba(83,197,224,0.06); }
.chat-item.active {
    background: rgba(83,197,224,0.1);
    border-left-color: #53C5E0;
}
.chat-item-body { flex: 1; min-width: 0; }
.chat-item-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2px;
}
.chat-item-name {
    font-size: 0.875rem;
    font-weight: 700;
    color: #eaf6fb;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.chat-item-time {
    font-size: 0.7rem;
    color: #4a7a8a;
    font-weight: 600;
    flex-shrink: 0;
}
.chat-item-meta {
    font-size: 0.73rem;
    color: #53C5E0;
    font-weight: 700;
    margin-bottom: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    text-transform: capitalize;
}
.chat-item-preview {
    font-size: 0.78rem;
    color: #6a9eaa;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Avatar */
.avatar-stack { position: relative; flex-shrink: 0; }
.avatar-img {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: linear-gradient(135deg, #00313d, #005068);
    border: 2px solid rgba(83,197,224,0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 1rem;
    color: #53C5E0;
    overflow: hidden;
}
.avatar-img img { width: 100%; height: 100%; object-fit: cover; }
.online-dot {
    position: absolute;
    bottom: 1px;
    right: 1px;
    width: 11px;
    height: 11px;
    border-radius: 50%;
    background: #64748b;
    border: 2px solid #00232b;
}
.online-dot.visible { background: #10b981; }

/* ===== MAIN PANEL ===== */
.chat-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: #00151b;
    overflow: hidden;
    position: relative;
}

/* Chat Header */
.chat-header {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    padding: 0.75rem 1.25rem;
    background: rgba(0,35,43,0.9);
    border-bottom: 1px solid rgba(83,197,224,0.12);
    flex-shrink: 0;
    z-index: 10;
    min-height: 64px;
}
.chat-header-info { flex: 1; min-width: 0; }
.chat-header-name {
    font-size: 0.95rem;
    font-weight: 800;
    color: #eaf6fb;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.status-pill {
    font-size: 0.6rem;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 999px;
    background: rgba(16,185,129,0.18);
    color: #10b981;
    border: 1px solid rgba(16,185,129,0.3);
    letter-spacing: 0.05em;
    text-transform: uppercase;
}
.chat-actions { display: flex; align-items: center; gap: 0.5rem; }
.action-btn {
    width: 36px; height: 36px;
    border-radius: 10px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(83,197,224,0.15);
    color: #8bbdcc;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    transition: all 0.18s;
    font-size: 1rem;
}
.action-btn:hover { background: rgba(83,197,224,0.12); color: #53C5E0; border-color: rgba(83,197,224,0.3); }

/* Dropdown */
.dropdown-menu {
    position: absolute;
    right: 0; top: calc(100% + 8px);
    background: #00313d;
    border: 1px solid rgba(83,197,224,0.2);
    border-radius: 12px;
    min-width: 180px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.4);
    z-index: 100;
    overflow: hidden;
    display: none;
}
.dropdown-item {
    padding: 0.75rem 1rem;
    font-size: 0.85rem;
    color: #c2dfeb;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.625rem;
    border-bottom: 1px solid rgba(83,197,224,0.1);
    transition: background 0.15s;
}
.dropdown-item:last-child { border-bottom: none; }
.dropdown-item:hover { background: rgba(83,197,224,0.1); color: #eaf6fb; }

/* ===== MESSAGES ===== */
#messageBox {
    flex: 1;
    overflow-y: auto;
    padding: 1.25rem 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
#messageBox::-webkit-scrollbar { width: 5px; }
#messageBox::-webkit-scrollbar-track { background: transparent; }
#messageBox::-webkit-scrollbar-thumb { background: rgba(83,197,224,0.2); border-radius: 4px; }

/* Message Row */
.msg-row {
    display: flex;
    align-items: flex-end;
    gap: 0.625rem;
    max-width: 92%;
    position: relative;
    margin-bottom: 0.75rem;
}
.msg-row.self { justify-content: flex-end; margin-left: auto; }
.msg-row.other { justify-content: flex-start; margin-right: auto; }
.msg-row.system {
    margin: 0.75rem auto;
    max-width: 90%;
    justify-content: center;
}
.msg-row.grouped-msg-next .msg-avatar { visibility: hidden; }

.msg-avatar {
    width: 32px; height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, #00313d, #005068);
    border: 1.5px solid rgba(83,197,224,0.2);
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 0.8rem;
    color: #53C5E0;
    flex-shrink: 0;
    overflow: hidden;
}
.msg-avatar img { width:100%; height:100%; object-fit:cover; }

.msg-content-col {
    position: relative;
    min-width: 0;
    max-width: 65%;
    z-index: 2;
}
.msg-row.self .msg-content-col {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    width: auto;
}
.msg-row.other .msg-content-col {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
}

.msg-sender-info {
    font-size: 0.7rem;
    font-weight: 700;
    color: #53C5E0;
    margin-bottom: 2px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.role-badge {
    font-size: 0.55rem;
    font-weight: 800;
    padding: 1px 6px;
    border-radius: 999px;
    background: #32a1c4;
    color: #fff;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

/* Bubble */
.msg-bubble {
    padding: 0.65rem 1rem;
    border-radius: 20px;
    font-size: 0.92rem;
    font-weight: 500;
    line-height: 1.5;
    overflow-wrap: break-word;
    word-break: break-word;
    white-space: normal;
    display: inline-block;
    width: auto;
    max-width: 100%;
    box-shadow: 0 4px 15px rgba(0,0,0,0.12);
    transition: all 0.2s ease;
}
.msg-bubble:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(0,0,0,0.18); }
.msg-row.other .msg-bubble {
    background: rgba(0,49,61,0.85);
    border: 1px solid rgba(83,197,224,0.15);
    color: #dff3fa;
    border-bottom-left-radius: 4px;
}
.msg-row.self .msg-bubble {
    background: linear-gradient(135deg, #1a5a72, #0d3d52);
    border: 1px solid rgba(83,197,224,0.3);
    color: #ffffff;
    border-bottom-right-radius: 4px;
}
.msg-row.system .msg-bubble {
    background: rgba(83,197,224,0.07);
    border: 1px solid rgba(83,197,224,0.15);
    color: #8bbdcc;
    font-size: 0.78rem;
    text-align: center;
    border-radius: 999px;
    padding: 0.3rem 1rem;
}

/* Reply preview inside bubble */
.reply-preview-bubble {
    display: block;
    background: rgba(0,0,0,0.2);
    border-left: 3px solid #53C5E0;
    border-radius: 6px;
    padding: 4px 8px;
    margin-bottom: 6px;
    font-size: 0.75rem;
    color: #a0ccd8;
    text-decoration: none;
    cursor: pointer;
}
.reply-preview-bubble:hover { background: rgba(0,0,0,0.3); }

/* Character Counter */
.char-counter {
    font-size: 0.65rem;
    color: #4a7a8a;
    font-weight: 700;
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    opacity: 0.8;
}
.char-counter.limit-near { color: #f59e0b; }
.char-counter.limit-reached { color: #ef4444; }

/* Hide global elements that overlap */
#floatingChatButton, .floating-chat-trigger, .floating-chat-circle, .chat-floating-button, 
[id*="floatingChat"], [class*="floating-chat"], .messenger-bubble, .floating-bubble,
.fixed-chat-widget, [class*="chat-widget"], #chat-widget-container,
 iframe[title*="Chat"], #tawk-container, .tawk-min-container { 
    display: none !important; visibility: hidden !important; opacity: 0 !important; pointer-events: none !important;
}

.chat-input {
    background: transparent; border: none; outline: none;
    flex: 1; color: #eaf6fb; font-size: 0.95rem;
    padding: 8px 0; width: 100%; min-width: 0;
    resize: none !important; max-height: 120px; line-height: 1.4;
    border: none !important; outline: none !important;
    height: 22px;
}
.chat-input::placeholder { color: #4a7a8a; }

/* Message meta / timestamp */
.msg-meta {
    font-size: 0.65rem;
    color: #4a7a8a;
    font-weight: 600;
}

/* Seen indicator */
.seen-wrapper { display: flex; justify-content: flex-end; }
.seen-indicator {
    width: 16px; height: 16px;
    border-radius: 50%;
    background-size: cover;
    background-color: #32a1c4;
    border: 1.5px solid rgba(83,197,224,0.4);
}

/* Hover actions & Reaction Picker */
.msg-row:hover .msg-action-bar, .msg-row.has-active-menu .msg-action-bar { opacity: 1; pointer-events: auto; }
.msg-action-bar {
    opacity: 0; pointer-events: none;
    display: flex; align-items: center; gap: 4px;
    padding: 2px 6px; border-radius: 999px;
    background: rgba(0, 35, 43, 0.9);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(83, 197, 224, 0.2);
    transition: opacity 0.2s;
    position: absolute; top: 50%; transform: translateY(-50%);
    z-index: 50;
}
.msg-row.other .msg-action-bar { left: calc(100% + 8px); }
.msg-row.self .msg-action-bar { right: calc(100% + 8px); flex-direction: row-reverse; }

.m-action-btn {
    width: 28px; height: 28px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 50%; color: #8bbdcc; cursor: pointer;
    transition: all 0.2s; font-size: 0.9rem;
}
.m-action-btn:hover { background: rgba(83, 197, 224, 0.15); color: #53C5E0; }

.reaction-picker {
    display: none; position: absolute; bottom: 100%; left: 50%;
    transform: translateX(-50%); background: #ffffff;
    padding: 0 18px; border-radius: 999px;
    box-shadow: 0 12px 40px rgba(0,0,0,0.4); z-index: 500;
    gap: 12px; border: 1px solid #e2e8f0;
    width: max-content; pointer-events: auto;
    align-items: center; justify-content: center;
    margin-bottom: 30px; height: 50px;
}
.reaction-picker.active { display: flex; animation: pickerPop 0.2s cubic-bezier(0.34, 1.56, 0.64, 1); }

/* More Actions Menu */
.m-more-menu {
    display: none; position: absolute; bottom: 100%; right: 0;
    background: #00313d; border: 1px solid rgba(83, 197, 224, 0.25);
    border-radius: 12px; padding: 6px 0; width: 160px;
    box-shadow: 0 12px 30px rgba(0,0,0,0.6); z-index: 400;
    margin-bottom: 15px;
}
.m-more-menu.active { display: block; animation: menuFade 0.2s ease; }
.m-menu-item {
    padding: 8px 16px; font-size: 0.85rem; font-weight: 700; color: #8bbdcc;
    display: flex; align-items: center; gap: 10px; cursor: pointer; transition: all 0.2s;
}
.m-menu-item:hover { background: rgba(83, 197, 224, 0.1); color: #53c5e0; }
.m-menu-item i { font-size: 1rem; }

@keyframes menuFade { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

.pinned-badge {
    position: absolute; top: -6px; right: -6px;
    width: 18px; height: 18px; background: #53c5e0;
    color: #fff; border-radius: 50%; display: flex;
    align-items: center; justify-content: center; font-size: 9px;
    border: 1px solid #00232b; box-shadow: 0 2px 4px rgba(0,0,0,0.3);
    z-index: 5;
}
@keyframes pickerPop { from { opacity: 0; transform: translateX(-50%) scale(0.8) translateY(10px); } to { opacity: 1; transform: translateX(-50%) scale(1) translateY(0); } }

.reaction-btn {
    background: none; border: none; font-size: 1.5rem; cursor: pointer;
    transition: transform 0.2s; padding: 0; line-height: 1;
}
.reaction-btn:hover { transform: scale(1.35) translateY(-4px); }

.reaction-display-container { margin-top: 4px; display: none; }
.reaction-display {
    display: inline-flex; align-items: center; gap: 4px;
    background: rgba(0, 49, 61, 0.95); border: 1px solid rgba(83, 197, 224, 0.25);
    border-radius: 999px; padding: 2px 8px; font-size: 0.85rem; cursor: default;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}
.reaction-display span { line-height: 1; }

/* ===== MEDIA GALLERY ===== */
.gallery-panel {
    position: absolute;
    right: 0; top: 0; bottom: 0;
    width: 280px;
    background: rgba(0,25,33,0.98);
    border-left: 1px solid rgba(83,197,224,0.15);
    display: flex;
    flex-direction: column;
    transform: translateX(100%);
    transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1);
    z-index: 20;
}
.gallery-panel.active { transform: translateX(0); }
.gallery-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 1rem 1rem 0.75rem;
    border-bottom: 1px solid rgba(83,197,224,0.1);
}
.gallery-title { font-size: 0.9rem; font-weight: 800; color: #eaf6fb; margin: 0; }
.gallery-tabs {
    display: flex;
    border-bottom: 1px solid rgba(83,197,224,0.1);
}
.g-tab {
    flex: 1; text-align: center;
    padding: 0.5rem;
    font-size: 0.75rem; font-weight: 700;
    color: #4a7a8a; cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.g-tab.active { color: #53C5E0; border-bottom-color: #53C5E0; }
.gallery-content { flex: 1; overflow-y: auto; padding: 0.75rem; }
.gallery-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 4px;
}
.gallery-item {
    aspect-ratio: 1;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    background: rgba(0,49,61,0.5);
    position: relative;
}
.gallery-item img, .gallery-item video { width:100%; height:100%; object-fit:cover; }
.gallery-item:hover { opacity: 0.85; }
.gallery-item .vid-icon {
    position: absolute; inset: 0;
    display: flex; align-items: center; justify-content: center;
    pointer-events: none;
}
.gallery-item .vid-icon svg { width: 24px; height: 24px; fill: white; opacity: 0.9; }

/* ===== CHAT FOOTER / INPUT ===== */
.chat-footer {
    padding: 10px 1.25rem;
    background: rgba(0, 35, 43, 0.95);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border-top: 1px solid rgba(83, 197, 224, 0.15);
    flex-shrink: 0;
    position: relative;
    z-index: 10;
}
.chat-footer-inner {
    max-width: 900px;
    margin: 0 auto;
    width: 100%;
}
.chat-footer.disabled { opacity: 0.5; pointer-events: none; }

.input-shell {
    display: flex;
    align-items: flex-end;
    gap: 8px;
    background: rgba(255, 255, 255, 0.04);
    border-radius: 18px;
    padding: 0 4px 0 12px;
    border: 1.5px solid rgba(83, 197, 224, 0.15);
    transition: all 0.2s;
    flex: 1;
}
.input-shell:focus-within {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(83, 197, 224, 0.45);
    box-shadow: 0 0 0 3px rgba(83, 197, 224, 0.08);
}

.input-icon-btn {
    display: flex; align-items: center; justify-content: center;
    width: 38px; height: 38px;
    cursor: pointer; color: #4a7a8a;
    transition: all 0.15s; background: transparent;
    border-radius: 12px;
}
.input-icon-btn:hover { background: rgba(83, 197, 224, 0.1); color: #53c5e0; }

.chat-input {
    flex: 1;
    background: transparent !important;
    border: none !important;
    outline: none !important;
    color: #eaf6fb !important;
    font-size: 0.95rem;
    font-weight: 500;
    padding: 0;
    width: 100%;
    min-width: 0;
    resize: none !important;
    max-height: 120px;
    line-height: 1.5;
    overflow-y: auto;
    font-family: inherit;
    height: 1.5em;
    display: block;
}
.chat-input::placeholder { color: #3a6070; }
.chat-input:disabled { cursor: not-allowed; }

.mic-btn {
    width: 42px; height: 42px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(83, 197, 224, 0.18);
    color: #4a7a8a;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    font-size: 1.1rem;
    transition: all 0.2s;
}
.mic-btn.recording { background: rgba(239, 68, 68, 0.15); border-color: rgba(239, 68, 68, 0.4); color: #ef4444; }
.mic-btn:hover { background: rgba(83, 197, 224, 0.1); color: #53c5e0; border-color: rgba(83, 197, 224, 0.35); }

.send-btn {
    background: var(--lp-accent, #32a1c4);
    color: #fff;
    border: none;
    width: 44px; height: 44px;
    border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(83, 197, 224, 0.2);
}
.send-btn:hover { opacity: 0.9; transform: scale(1.05); box-shadow: 0 6px 20px rgba(83, 197, 224, 0.38); }
.send-btn:active { transform: scale(0.96); }
.send-btn:disabled { background: rgba(255,255,255,0.08); cursor: not-allowed; transform: none; box-shadow: none; color: #3a6070; }

/* Modern Voice Player UI */
.voice-bubble-player {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    border-radius: 12px;
    min-width: 280px;
    max-width: 100%;
}
.msg-bubble:has(.voice-bubble-player) {
    padding: 0 !important;
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
}

.msg-row.other .voice-bubble-player { background: #f1f5f9; color: #1e293b; }
.msg-row.self .voice-bubble-player { background: rgba(255,255,255,0.1); color: #fff; }

.play-pause-btn {
    width: 32px; height: 32px;
    border-radius: 50%;
    background: #0a2530;
    border: none;
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    cursor: pointer;
    transition: transform 0.2s, background 0.2s;
    flex-shrink: 0;
}
.msg-row.self .play-pause-btn { background: #fff; color: #0a2530; }
.play-pause-btn:hover { transform: scale(1.1); opacity: 0.9; }

.v-waveform-container {
    flex: 1;
    height: 30px;
    position: relative;
    cursor: pointer;
    display: flex;
    align-items: center;
}
.v-waveform-canvas {
    width: 100%;
    height: 100%;
    display: block;
}
.v-duration {
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    min-width: 35px;
    text-align: right;
}
.msg-row.self .v-duration { color: rgba(255,255,255,0.8); }

/* Recording Panel */
.recording-panel {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(239, 68, 68, 0.05);
    border: 1px solid rgba(239, 68, 68, 0.1);
    border-radius: 12px;
    padding: 2px 10px;
    margin: 0 4px;
    overflow: hidden;
}
.rec-timer { font-family: 'JetBrains Mono', monospace; font-weight: 800; color: #ef4444; font-size: 0.85rem; min-width: 35px; }
.rec-pulse { width: 8px; height: 8px; background: #ef4444; border-radius: 50%; animation: pulse-rec 1s infinite; flex-shrink: 0; }
#recordingCanvas {
    flex: 1;
    height: 30px;
    background: transparent;
}

/* Voice Preview before sending */
#voicePreviewArea {
    display: none;
    align-items: center;
    gap: 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 6px 12px;
    margin: 0 4px;
    flex: 1;
}

@keyframes pulse-rec {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(1.3); }
}

/* Reply preview box */
#replyPreviewBox {
    display: none;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 1.25rem;
    background: rgba(0,35,43,0.95);
    border-top: 1px solid rgba(83,197,224,0.12);
}
.reply-content-box {
    flex: 1;
    background: rgba(83,197,224,0.07);
    border-left: 3px solid #53C5E0;
    border-radius: 6px;
    padding: 4px 10px;
}
.reply-heading { font-size: 0.65rem; font-weight: 800; color: #53C5E0; text-transform: uppercase; letter-spacing: 0.05em; }
.reply-text-preview { font-size: 0.8rem; color: #8bbdcc; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cancel-reply-btn {
    background: none; border: none; color: #4a7a8a; cursor: pointer;
    padding: 4px; border-radius: 6px; transition: color 0.15s;
}
.cancel-reply-btn:hover { color: #ff6b6b; }

/* Media mobile */
.mobile-menu-btn { display: none; }

/* Chat image */
.chat-image-wrap { cursor: pointer; border-radius: 12px; overflow: hidden; max-width: 260px; }
.chat-image-wrap img { width: 100%; display: block; }

/* Utility */
.hidden { display: none !important; }

/* Mobile */
@media (max-width: 900px) {
    .chat-sidebar {
        position: absolute; left: 0; top: 0; bottom: 0; z-index: 30;
        transform: translateX(-100%);
        box-shadow: 4px 0 20px rgba(0,0,0,0.4);
    }
    .chat-sidebar.open { transform: translateX(0); }
    .mobile-menu-btn { display: flex; }
}
/* Chat footer integration */
/* Order Details Modal Glassmorphism */
.order-details-modal-overlay {
    background: rgba(0, 5, 10, 0.65) !important;

    position: fixed;
    inset: 0;
    z-index: 2000000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}





.order-details-modal-content {
    background: rgba(0, 20, 35, 0.45) !important;

    backdrop-filter: blur(32px);
    -webkit-backdrop-filter: blur(32px);
    border: 1px solid rgba(83, 197, 224, 0.15) !important;
    box-shadow: 0 40px 100px rgba(0,0,0,0.8), inset 0 0 0 1px rgba(255,255,255,0.05) !important;
    max-width: 850px !important;
    width: 94% !important;
    max-height: 85vh !important;
    display: flex;
    flex-direction: column;
    position: relative;
    overflow: hidden;
    border-radius: 0 !important;
}





.order-details-header {
    padding: 0.85rem 1.25rem;
    border-bottom: 1px solid rgba(83, 197, 224, 0.1) !important;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}


.order-details-header h2 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 800;
    color: #eaf6fb !important;
    letter-spacing: -0.01em;
}


.order-details-close {
    width: 28px;
    height: 28px;
    background: #ffffff !important;
    border: none !important;
    cursor: pointer;
    border-radius: 50% !important;
    color: #000000 !important;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.order-details-close:hover {
    background: #f1f5f9 !important;
}



.pf-spec-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
}



@media (max-width: 768px) {
    .pf-spec-grid { grid-template-columns: repeat(2, 1fr); }
}

.pf-spec-box {
    background: rgba(255, 255, 255, 0.035);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(83, 197, 224, 0.08) !important;
    border-radius: 0 !important;
    padding: 10px 14px;
    transition: all 0.2s;
}



.pf-spec-box:hover {
    background: rgba(255, 255, 255, 0.08);
}

.pf-spec-key {
    font-size: 0.55rem;
    color: #53c5e0;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 2px;
    opacity: 0.7;
}

.pf-spec-val {
    font-size: 0.85rem;
    color: #eaf6fb;
    font-weight: 700;
}

.status-pill {
    border-radius: 0 !important;
}

.notes-box {
    margin-top: 0.5rem;
    padding: 0.85rem;
    border-radius: 0 !important;
    background: rgba(83, 197, 224, 0.02);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(83, 197, 224, 0.1) !important;
}






.ft-footer {
    margin-top: 0 !important;
}
@keyframes highlightMsg {
    0% { background: rgba(83,197,224,0.3); transform: scale(1.02); }
    100% { background: transparent; transform: scale(1); }
}
/* Details Modal (Forward/Pinned) Fix */
.details-modal-overlay {
    position: fixed; inset: 0;
    background: rgba(0, 5, 8, 0.85);
    backdrop-filter: blur(8px);
    display: none; align-items: center; justify-content: center;
    z-index: 10000;
}
.details-modal-overlay.active { display: flex; }
.details-modal-panel {
    background: #000a0d;
    border: 1px solid rgba(83, 197, 224, 0.2);
    border-radius: 24px;
    width: 95%; max-width: 450px;
    box-shadow: 0 20px 50px rgba(0,0,0,0.5);
    overflow: hidden;
    overflow-wrap: anywhere;
    display: flex; flex-direction: column;
}
</style>



<div id="chat-outer">
    <div class="glass-shell" id="chatShell">
        <!-- Sidebar -->
        <aside class="chat-sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2 class="sidebar-title m-0">Messages</h2>
                
                <div class="search-container">
                    <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-4.35-4.35m1.85-5.15a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2.5"/></svg>
                    <input type="text" id="convSearch" placeholder="Search orders or keywords..." autocomplete="off">
                </div>
            </div>

            <div class="conv-tabs">
                <div class="conv-tab active" id="tab-active" onclick="switchTab(false)">Active</div>
                <div class="conv-tab" id="tab-archived" onclick="switchTab(true)">Archived</div>
            </div>

            <div id="convList">
                <div class="p-8 text-center"><span class="animate-pulse">Loading chats...</span></div>
            </div>
        </aside>

        <!-- Main Chat Area -->
        <main class="chat-main">
            <!-- Header -->
            <header class="chat-header">
                <button type="button" class="action-btn mobile-menu-btn" onclick="toggleSidebar(true)">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16" stroke-width="2"/></svg>
                </button>
                <div class="avatar-stack">
                    <div class="avatar-img" id="activeAvatar">?</div>
                    <div class="online-dot" id="activeOnlineDot"></div>
                </div>
                <div class="chat-header-info">
                    <h3 class="chat-header-name m-0">
                        <span id="activeName">Select a chat</span>
                        <span class="status-pill" id="activeOnlineStatus" style="display:none;">Online</span>
                    </h3>
                    <p class="m-0 text-sm opacity-60" id="activeMeta">Choose an order to start</p>
                </div>
                <!-- Archived Sync Notice -->
                <div id="archivedNotice" style="display:none; padding: 4px 12px; border-radius: 8px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); font-size: 0.7rem; font-weight: 800; color: #ef4444; margin-right: 1rem;">
                    <i class="bi bi-archive-fill mr-1"></i> ARCHIVED
                </div>
                <div class="chat-actions">
                    <!-- Call Actions -->
                    <button class="action-btn call-btns" id="btn-voice-call" onclick="initiateCall('voice')" title="Voice Call" style="display:none;">
                        <i class="bi bi-telephone"></i>
                    </button>
                    <button class="action-btn call-btns" id="btn-video-call" onclick="initiateCall('video')" title="Video Call" style="display:none;">
                        <i class="bi bi-camera-video"></i>
                    </button>

                    <div class="unified-menu" style="position:relative;">
                        <button class="action-btn" onclick="toggleChatMenu(event)" id="chatMenuBtn" title="More Options">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <div class="dropdown-menu" id="chatDropdown">
                            <div class="dropdown-item" onclick="toggleMediaGallery(true)">
                                <i class="bi bi-images"></i> Shared Media
                            </div>
                            <div class="dropdown-item" id="archiveLabel" onclick="if(activeOrderId) toggleArchive(activeOrderId, !currentArchivedState)">
                                <i class="bi bi-archive"></i> Archive
                            </div>
                            <div class="dropdown-item" onclick="if(activeOrderId) openOrderDetailsModal(activeOrderId)">
                                <i class="bi bi-info-circle"></i> Order Details
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Pinned Messages Bar -->
            <div id="pinnedBar" style="display:none; position:sticky; top:0; z-index:15; background:rgba(0,35,43,0.9); backdrop-filter:blur(10px); border-bottom:1px solid rgba(83,197,224,0.15); padding:8px 1.25rem; align-items:center; justify-content:space-between; cursor:pointer; transition:all 0.2s;">
                <div style="display:flex; align-items:center; gap:10px; flex:1; min-width:0;">
                    <i class="bi bi-pin-angle-fill" style="color:#53c5e0; font-size:0.9rem;"></i>
                    <span id="pinnedCountText" style="font-size:0.75rem; font-weight:700; color:#eaf6fb; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">0 pinned messages</span>
                </div>
                <i class="bi bi-chevron-right" style="color:#4a7a8a; font-size:0.8rem;"></i>
            </div>

            <!-- Messages Area -->
            <div id="messageBox">
                <div class="flex-1 flex items-center justify-center text-center opacity-40 p-12" id="chatWelcome">
                    <div>
                        <div style="font-size:4rem; margin-bottom:1rem;">💬</div>
                        <h3 class="m-0">Your Conversations</h3>
                        <p class="m-0 text-sm mt-2">Pick an order from the left to start messaging our team.</p>
                    </div>
                </div>
            </div>

            <!-- Shared Media Gallery Panel -->
            <div id="mediaGallery" class="gallery-panel">
                <div class="gallery-header">
                    <h4 class="gallery-title">Shared Media</h4>
                    <button onclick="toggleMediaGallery(false)" class="action-btn" style="border:none; background:transparent;">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="2.5"/></svg>
                    </button>
                </div>
                <div class="gallery-tabs">
                    <div class="g-tab active" id="gTabImages" onclick="switchGalleryTab('image')">Images</div>
                    <div class="g-tab" id="gTabVideos" onclick="switchGalleryTab('video')">Videos</div>
                </div>
                <div class="gallery-content" id="galleryContent">
                    <div class="gallery-grid" id="mediaGrid">
                        <!-- Media items injected via JS -->
                    </div>
                </div>
            </div>

            <!-- Previews -->
            <div id="imgPreviews" style="display:none; padding: 10px 1.5rem; background: rgba(0,35,43,0.9); border-top:1px solid rgba(83,197,224,0.1); display:flex; gap:8px;"></div>
            
            <div id="replyPreviewBox" style="max-width:900px; margin: 0 auto 10px;">
                <div class="reply-content-box overflow-hidden">
                    <div class="reply-heading">Replying to message</div>
                    <div class="reply-text-preview" id="replyPreviewText" style="overflow-wrap:anywhere;"></div>
                </div>
                <button type="button" class="cancel-reply-btn" onclick="cancelReply()">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="2.5"/></svg>
                </button>
            </div>

            <!-- Footer / Input -->
            <footer class="chat-footer disabled" id="chatFooter">
                <div class="chat-footer-inner flex items-center" style="gap: 12px !important;">
                    <button class="mic-btn" id="startRecord" title="Record Voice">
                        <i class="bi bi-mic"></i>
                    </button>

                    <div class="input-shell flex-1" id="inputContainer" style="position:relative; display:flex; align-items:flex-end; gap:10px;">
                        <label class="input-icon-btn m-0" title="Send Picture" style="margin-bottom:6px !important;">
                            <input type="file" id="imgInput" accept="image/*,video/mp4,video/webm,video/quicktime" multiple class="hidden">
                            <i class="bi bi-image"></i>
                        </label>
                        <textarea id="chatInput" class="chat-input" placeholder="Type a message..." autocomplete="off" disabled maxlength="500" rows="1"></textarea>
                        <span id="charCount" class="char-counter" style="bottom:6px; top:auto; transform:none;">0/500</span>
                    </div>

                    <div class="recording-panel hidden" id="recordStatus">
                        <div class="rec-pulse" title="Recording"></div>
                        <canvas id="recordingCanvas"></canvas>
                        <span class="rec-timer" id="timer">0:00</span>
                    </div>

                    <div id="voicePreviewArea">
                        <button type="button" class="play-pause-btn" id="previewPlayBtn" onclick="togglePreviewPlayback()">
                            <i class="bi bi-play-fill" id="previewPlayIcon"></i>
                        </button>
                        <div class="v-waveform-container" id="previewWaveformContainer">
                            <canvas id="previewWaveformCanvas" class="v-waveform-canvas"></canvas>
                        </div>
                        <span class="v-duration" id="previewDuration">0:00</span>
                    </div>

                    <button class="mic-btn hidden" id="cancelRecord" title="Delete Recording" style="background:#fee2e2; border-color:#fecaca; color:#ef4444;">
                        <i class="bi bi-trash3"></i>
                    </button>

                    <button class="mic-btn hidden" id="stopRecord" title="Stop Recording" style="background:#dcfce7; border-color:#bbf7d0; color:#16a34a;">
                        <i class="bi bi-stop-circle-fill"></i>
                    </button>

                    <button type="button" class="send-btn" id="sendBtn" title="Send Message">
                        <i class="bi bi-send" style="font-size: 1.1rem;"></i>
                    </button>
                </div>
            </footer>
        </main>
    </div>
</div>

<!-- Lightbox for images -->
<div id="chatLightbox" onclick="closeChatLightbox()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.95);z-index:9000;align-items:center;justify-content:center;padding:2rem;cursor:pointer;">
    <div style="position:relative;max-width:95vw;max-height:95vh;display:flex;flex-direction:column;align-items:center;" onclick="event.stopPropagation()">
        <img id="chatLightboxImg" src="" alt="Enlarged" style="max-width:100%;max-height:80vh;border-radius:12px;box-shadow:0 0 50px rgba(0,0,0,0.5);display:none;object-fit:contain;">
        <video id="chatLightboxVideo" controls style="max-width:100%;max-height:80vh;border-radius:12px;box-shadow:0 0 50px rgba(0,0,0,0.5);display:none;background:#000;outline:none;" preload="metadata"></video>
        <div style="display:flex; justify-content:center; gap:1.5rem; margin-top:1.5rem;">
            <a id="lightboxDownload" href="" download class="action-btn" style="width:auto; padding:0 20px; font-size:0.9rem; font-weight:700;">&#x2B07; Download</a>
            <button onclick="closeChatLightbox()" class="action-btn" style="width:auto; padding:0 20px; font-size:0.9rem; font-weight:700;">&#x2715; Close</button>
        </div>
    </div>
</div>

<!-- Order Details Modal -->
<div id="orderDetailsModal" class="order-details-modal-overlay" style="display:none;" onclick="closeOrderDetailsModal()">

    <div class="order-details-modal-content" onclick="event.stopPropagation()">
        <div class="order-details-header">
            <h2>Order Information</h2>
            <button type="button" class="order-details-close" onclick="closeOrderDetailsModal()">
                <svg style="width:20px;height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="2.5"/></svg>
            </button>
        </div>
        <div id="orderDetailsModalBody" style="flex:1;overflow-y:auto;padding:1.75rem; color:#eaf6fb;">
            <div class="text-center py-12" id="orderDetailsLoading">
                <div class="inline-block animate-spin" style="width:2rem; height:2rem; border:3px solid rgba(83,197,224,0.3); border-top-color:#53c5e0; border-radius:50%; margin-bottom:1rem;"></div>
                <div style="font-weight:700; color:#4a7a8a;">Loading details...</div>
            </div>
            <div id="orderDetailsContent" style="display:none;"></div>
        </div>
    </div>
</div>

<script>
window.baseUrl = '<?php echo BASE_URL; ?>';
let activeOrderId = <?php echo $order_id ?: 'null'; ?>;
let viewingArchived = false;
let currentArchivedState = false;
let partnerAvatarUrl = null;
let lastMsgId = 0;
let pollTimer = null;
let listTimer = null;
let files = [];
let replyToMessageId = null;
let currentReactions = [];
const REACTION_EMOJIS = {
    'like': '👍', 'love': '❤️', 'haha': '😂', 'wow': '😮', 'sad': '😢', 'angry': '😡'
};

// --- Call Integration ---
let callSystem = null;
function initCallSystem() {
    if (callSystem) return;
    callSystem = new PrintFlowCall({
        userId: <?php echo $customer_id; ?>,
        role: 'Customer',
        userName: '<?php echo str_replace("'", "\\'", $_SESSION['user_name'] ?? "Customer"); ?>',
        userAvatar: '<?php 
            $u = get_logged_in_user(); 
            echo ($u && !empty($u['avatar'])) ? (BASE_URL . "/" . $u['avatar']) : ""; 
        ?>'
    });
}

function initiateCall(type) {
    if (!activeOrderId) return;
    const fd = new FormData();
    fd.append('order_id', activeOrderId);
    api('/public/api/chat/status.php', 'POST', fd)
        .then(res => {
            if (!res.partner) { alert("Partner is offline or unavailable."); return; }
            const pId = res.partner.id;
            const pName = res.partner.name;
            const pAvatar = res.partner.avatar ? (baseUrl + "/public/assets/uploads/profiles/" + res.partner.avatar) : "";
            
            callSystem.startCall(pId, 'Staff', type, activeOrderId, pName, pAvatar);
        });
}

const activeOrderIdOriginal = activeOrderId;

// --- API Helpers ---

function api(path, method = 'GET', body = null) {
    const opts = { credentials: 'same-origin', method };
    if (body) opts.body = (body instanceof FormData) ? body : JSON.stringify(body);
    return fetch(window.baseUrl + path, opts).then(r => {
        if (!r.ok) throw new Error('API request failed');
        return r.json();
    }).catch(e => {
         console.error('Chat API Error:', e);
         return { success: false, error: e.message };
    });
}

// --- Sidebar Logic ---

function loadConversations() {
    const q = document.getElementById('convSearch').value;
    api(`/public/api/chat/list_conversations.php?archived=${viewingArchived ? 1 : 0}&q=${encodeURIComponent(q)}`)
        .then(data => {
            if (!data.success) {
                document.getElementById('convList').innerHTML = '<div class="p-8 text-center text-red-400">Error loading list</div>';
                return;
            }
            renderConvList(data.conversations);
            
            // If the chat window isn't officially "open" but we have activeOrderId,
            // find it in the list to sync UI
            if (activeOrderId && !window.uiOpenedChat) {
                const c = data.conversations.find(x => x.order_id == activeOrderId);
                if (c) {
                    const name = c.staff_name || 'PrintFlow Team';
                    const meta = c.service_name || 'Order';
                    openChatComponent(c.order_id, name, meta, c.is_archived);
                }
            }
        });
}

function renderConvList(items) {
    const list = document.getElementById('convList');
    if (!items || !items.length) {
        list.innerHTML = `<div class="p-12 text-center opacity-40"><p>No ${viewingArchived ? 'archived' : ''} chats found</p></div>`;
        return;
    }
    list.innerHTML = items.map(c => {
        const isActive = activeOrderId === c.order_id;
        const onlineClass = c.is_online ? 'visible' : '';
        const initial = (c.staff_name || 'P')[0];
        const avatarHtml = c.staff_avatar 
            ? `<img src="${window.baseUrl}/${c.staff_avatar}" class="w-full h-full object-cover" onerror="this.onerror=null; this.outerHTML='<div class=&quot;avatar-initials&quot;>${initial}</div>';">` 
            : `<div class="avatar-initials">${initial}</div>`;

        return `
            <div class="chat-item ${isActive ? 'active' : ''}" 
                 data-order-id="${c.order_id}" 
                 data-name="${escapeHtml(c.staff_name || 'PrintFlow Team')}" 
                 data-meta="${escapeHtml(c.service_name || 'Order')}"
                 data-avatar="${c.staff_avatar ? window.baseUrl + '/' + c.staff_avatar : ''}"
                 data-archived="${c.is_archived ? 1 : 0}">
                <div class="avatar-stack">
                    <div class="avatar-img">${avatarHtml}</div>
                    <div class="online-dot ${onlineClass}"></div>
                </div>
                <div class="chat-item-body">
                    <div class="chat-item-top">
                        <span class="chat-item-name">${escapeHtml(c.staff_name || 'PrintFlow Team')}</span>
                        <span class="chat-item-time">${formatTimeAgo(c.last_message_at)}</span>
                    </div>
                    <div class="chat-item-meta">${escapeHtml(c.service_name || '').toLowerCase()}</div>
                    <div class="chat-item-preview">
                        ${c.unread_count > 0 ? `<span class="bg-[#0a2530] text-[#fff] text-[0.65rem] px-1.5 py-0.5 rounded-full font-black mr-1">${c.unread_count}</span>` : ''}
                        ${escapeHtml(c.last_message || 'Start chatting...')}
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

// Event Delegation for Conversation List
document.getElementById('convList').addEventListener('click', function(e) {
    const item = e.target.closest('.chat-item');
    if (!item) return;
    
    const id = parseInt(item.dataset.orderId);
    const name = item.dataset.name;
    const meta = item.dataset.meta;
    const avatar = item.dataset.avatar;
    const archived = item.dataset.archived === '1';
    
    openChatComponent(id, name, meta, archived, avatar);
});


function switchTab(isArchived) {
    viewingArchived = isArchived;
    document.getElementById('tab-active').classList.toggle('active', !isArchived);
    document.getElementById('tab-archived').classList.toggle('active', isArchived);
    loadConversations();
}

// --- Chat Logic ---

function openChatComponent(id, name, meta, isArchived, avatar = '') {
    // Instant UI update first
    activeOrderId = id;
    window.uiOpenedChat = true;
    
    // Update UI immediately
    const welcome = document.getElementById('chatWelcome');
    if (welcome) welcome.style.display = 'none';

    const footer = document.getElementById('chatFooter');
    if (footer) footer.classList.remove('disabled');

    const input = document.getElementById('chatInput');
    if (input) {
        input.disabled = false;
        input.placeholder = 'Type a message...';
    }
    
    const archiveBtn = document.getElementById('archiveBtn');
    if (archiveBtn) archiveBtn.style.display = 'flex';

    const infoBtn = document.getElementById('infoBtn');
    if (infoBtn) infoBtn.style.display = 'flex';
    
    document.getElementById('activeName').textContent = name;
    document.getElementById('activeMeta').textContent = meta.toLowerCase();
    
    // Update Avatar
    const avatarEl = document.getElementById('activeAvatar');
    if (avatar) {
        avatarEl.innerHTML = `<img src="${avatar}" class="w-full h-full object-cover rounded-full" onerror="this.onerror=null; this.parentElement.textContent='${name[0]}';">`;
    } else {
        avatarEl.textContent = name[0];
    }
    
    // Show Call Buttons
    document.querySelectorAll('.call-btns').forEach(el => el.style.display = 'flex');
    initCallSystem();

    if (archiveBtn) {
        archiveBtn.title = isArchived ? 'Unarchive' : 'Archive';
        archiveBtn.onclick = () => toggleArchive(id, !isArchived);
        archiveBtn.innerHTML = isArchived ? 
            '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 8h14M10 12h4M4 8l1 12h14l1-12M10 5h4" stroke-width="2"/></svg>' :
            '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 8h14M10 12h4M4 8l1 12h14l1-12M10 5h4" stroke-width="2" stroke-linecap="round"/></svg>';
    }

    // Set initial archive UI
    updateArchiveUI(!!isArchived);

    // Close any open menus
    const menu = document.getElementById('chatDropdown');
    if (menu) menu.style.display = 'none';

    document.getElementById('messageBox').innerHTML = '<div class="p-8 text-center opacity-30">Loading messages...</div>';
    
    // Update active state in sidebar immediately
    document.querySelectorAll('.chat-item').forEach(item => item.classList.remove('active'));
    const activeItem = document.querySelector(`.chat-item[data-order-id="${id}"]`);
    if (activeItem) activeItem.classList.add('active');
    
    // Reset and load data
    lastMsgId = 0;
    loadMessages();
    setupPoll();
    
    if (window.innerWidth <= 900) toggleSidebar(false);
    history.replaceState(null, '', `?order_id=${id}`);
}

function toggleChatMenu(e) {
    if (e) e.stopPropagation();
    const menu = document.getElementById('chatDropdown');
    if (menu) {
        menu.style.display = (menu.style.display === 'none') ? 'block' : 'none';
    }
}

window.addEventListener('click', () => {
    const menu = document.getElementById('chatDropdown');
    if (menu) menu.style.display = 'none';
});

function updateArchiveUI(isArchived) {
    currentArchivedState = isArchived;
    const notice = document.getElementById('archivedNotice');
    const label = document.getElementById('archiveLabel');
    if (notice) notice.style.display = isArchived ? 'block' : 'none';
    if (label) {
        label.innerHTML = isArchived ? '<i class="bi bi-arrow-up-circle"></i> Unarchive' : '<i class="bi bi-archive"></i> Archive';
    }
}

function toggleArchive(id, st) {
    const fd = new FormData();
    fd.append('order_id', id);
    fd.append('archive', st ? 1 : 0);
    api('/public/api/chat/set_archived.php', 'POST', fd).then(res => {
        if (res.success) {
            updateArchiveUI(st);
            loadConversations();
        }
    });
}

function loadMessages() {
    if (!activeOrderId) return;
    const box = document.getElementById('messageBox');
    api(`/public/api/chat/fetch_messages.php?order_id=${activeOrderId}&last_id=${lastMsgId}&is_active=1`)
        .then(data => {
            if (!data.success) {
                console.error("Chat API Error:", data.error);
                clearInterval(pollTimer); // STOP LOOP IF ERROR
                return;
            }
            
            if (lastMsgId === 0) box.innerHTML = '';
            
            data.messages.forEach(m => {
                appendMessageUI(m);
                lastMsgId = Math.max(lastMsgId, m.id);
            });
            
            if (data.reactions) {
                currentReactions = data.reactions;
                renderAllReactions();
            }

            // Update partner status
            const dot = document.getElementById('activeOnlineDot');
            const pill = document.getElementById('activeOnlineStatus');
            if (dot) dot.classList.toggle('visible', !!data.partner?.is_online);
            if (pill) pill.style.display = data.partner?.is_online ? 'block' : 'none';
            if (data.partner && data.partner.avatar) {
                partnerAvatarUrl = window.baseUrl + '/' + data.partner.avatar;
            }
            
            if (data.is_archived !== undefined) updateArchiveUI(data.is_archived);
            if (data.messages.length) scrollToBottom(lastMsgId === 0 ? false : true, lastMsgId === 0);
            
            if (data.last_seen_message_id !== undefined) {
                updateCustomerSeenIndicators(data.last_seen_message_id);
            }

            // Update Pinned Bar
            updatePinnedBar(data.pinned_messages || []);
        });
}

function updatePinnedBar(pinned) {
    const bar = document.getElementById('pinnedBar');
    const text = document.getElementById('pinnedCountText');
    if (!pinned || pinned.length === 0) {
        bar.style.display = 'none';
        return;
    }
    bar.style.display = 'flex';
    text.textContent = pinned.length === 1 ? '1 pinned message' : `${pinned.length} pinned messages`;
    bar.onclick = () => openPinnedModal(pinned);
}

function openPinnedModal(pinned) {
    if (!document.getElementById('pinnedModal')) {
        const div = document.createElement('div');
        div.id = 'pinnedModal';
        div.className = 'order-details-modal-overlay';
        div.innerHTML = `
            <div class="order-details-modal-content" style="max-width:450px;">
                <div class="order-details-header">
                    <h2>Pinned Messages</h2>
                    <button class="order-details-close" onclick="document.getElementById('pinnedModal').style.display='none'">&times;</button>
                </div>
                <div id="pinnedList" style="padding:1rem; max-height:500px; overflow-y:auto; display:flex; flex-direction:column; gap:10px;"></div>
            </div>
        `;
        document.body.appendChild(div);
    }
    const modal = document.getElementById('pinnedModal');
    modal.style.display = 'flex';
    const list = document.getElementById('pinnedList');
    list.innerHTML = pinned.map(m => `
        <div onclick="goToMessage(${m.id}); document.getElementById('pinnedModal').style.display='none'" style="padding:12px; border-radius:12px; background:rgba(83,197,224,0.05); border:1px solid rgba(83,197,224,0.1); cursor:pointer; transition:all 0.2s;">
            <div style="font-size:0.7rem; color:#53c5e0; font-weight:800; margin-bottom:4px;">${m.sender_name} • ${m.created_at}</div>
            <div style="font-size:0.85rem; color:#eaf6fb; line-height:1.4;">${escapeHtml(m.message || (m.image_path ? '📸 Attachment' : 'Message'))}</div>
        </div>
    `).join('');
}

function goToMessage(id) {
    const el = document.getElementById(`msg-${id}`);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.style.animation = 'highlightMsg 2s ease';
    }
}

function appendMessageUI(m) {
    const box = document.getElementById('messageBox');
    if (document.getElementById(`msg-${m.id}`)) return;

    // Check for grouping
    const prevRow = box.lastElementChild;
    const isGrouped = prevRow && !m.is_system && 
                      prevRow.getAttribute('data-sender') === (m.is_self ? 'self' : m.sender) && 
                      prevRow.getAttribute('data-time') === m.created_at;

    const row = document.createElement('div');
    row.id = `msg-${m.id}`;
    row.className = `msg-row ${m.is_system ? 'system' : (m.is_self ? 'self' : 'other')}`;
    row.setAttribute('data-sender', m.is_self ? 'self' : m.sender);
    row.setAttribute('data-time', m.created_at);

    if (isGrouped) {
        prevRow.classList.add('grouped-msg');
        row.classList.add('grouped-msg-next');
    }
    
    // Setup Avatar (Only show partner avatar, hide self-avatar)
    let avatarHtml = '';
    if (!m.is_system && !m.is_self) {
        const avSrc = m.sender_avatar ? `${window.baseUrl}/${m.sender_avatar}` : '';
        const initial = (m.sender_name || 'S')[0];
        
        if (m.sender_avatar) {
            avatarHtml = `<img src="${avSrc}" class="msg-avatar" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                          <div class="msg-avatar" style="display:none;">${initial}</div>`;
        } else {
            avatarHtml = `<div class="msg-avatar">${initial}</div>`;
        }
    }

    const isCallMsg = (m.message && m.message.includes('📞'));
    let colHtml = `<div class="msg-content-col" style="${isCallMsg ? 'max-width:none;' : ''}">`;
    
    // Sender Info (Name & Role)
    if (!m.is_self && !m.is_system) {
        const roleBadge = m.sender_role ? `<span class="role-badge">${m.sender_role}</span>` : '';
        colHtml += `<div class="msg-sender-info">${escapeHtml(m.sender_name || m.sender)} ${roleBadge}</div>`;
    }

    // Stable Action Bar (Emoji, Reply, More)
    if (!m.is_system) {
        const pickerHtml = Object.keys(REACTION_EMOJIS).map(key => 
            `<button class="reaction-btn" onclick="toggleReaction(${m.id}, '${key}')">${REACTION_EMOJIS[key]}</button>`
        ).join('');

        const msgB64 = safeBase64Encode(m.message || '');
        
        colHtml += `
        <div class="msg-action-bar">
            <!-- Emoji Picker Trigger -->
            <div class="m-action-btn" title="React" onclick="togglePicker(${m.id}, event)" style="position:relative;">
                <i class="bi bi-emoji-smile"></i>
                <div class="reaction-picker" id="picker-${m.id}">${pickerHtml}</div>
            </div>

            <!-- Reply Arrow -->
            <div class="m-action-btn" title="Reply" onclick="initReply(${m.id}, '${msgB64}', '${m.image_path ? 1 : 0}')">
                <i class="bi bi-reply-fill"></i>
            </div>

            <!-- More Options -->
            <div class="m-action-btn" title="More" onclick="toggleMoreMenu(${m.id}, event)" style="position:relative;">
                <i class="bi bi-three-dots"></i>
                <div class="m-more-menu" id="more-${m.id}">
                    <div class="m-menu-item" onclick="pinMessage(${m.id})">
                        <i class="bi bi-pin-angle"></i> Pin
                    </div>
                    <div class="m-menu-item" onclick="initForward(${m.id}, '${msgB64}', '${m.image_path ? 1 : 0}')">
                        <i class="bi bi-arrow-right-short"></i> Forward
                    </div>
                </div>
            </div>
        </div>`;
    }

    // Message Bubble
    colHtml += `<div class="msg-bubble" style="position:relative; ${isCallMsg ? 'max-width:none;' : ''}" id="bubble-${m.id}">`;
    
    // Pin Indicator
    if (m.is_pinned) {
        colHtml += `<div class="pinned-badge" title="Pinned Message"><i class="bi bi-pin-fill"></i></div>`;
    }
    
    // Reply Preview within Bubble
    if (m.reply_id) {
        let previewContent = m.reply_image ? '📸 Photo' : (m.reply_message ? escapeHtml(m.reply_message) : 'Message');
        colHtml += `<a href="javascript:void(0)" onclick="document.getElementById('msg-${m.reply_id}')?.scrollIntoView({behavior: 'smooth', block: 'center'})" class="reply-preview-bubble">↳ Replying to: ${previewContent}</a>`;
    }

    if (m.message_type === 'voice') {
        const audioSrc = m.message_file || m.file_path || m.image_path;
        colHtml += `
        <div class="voice-bubble-player" id="voice-p-${m.id}">
            <button class="play-pause-btn" onclick="toggleVoicePlayer(${m.id}, '${audioSrc}')">
                <i class="bi bi-play-fill" id="v-icon-${m.id}" style="font-size: 1.2rem; margin-left: 2px;"></i>
            </button>
            <div class="v-waveform-container" onclick="seekVoice(${m.id}, event)">
                <canvas class="v-waveform-canvas" id="v-canvas-${m.id}" width="400" height="60"></canvas>
            </div>
            <span class="v-duration" id="v-dur-${m.id}">0:00</span>
            <audio id="v-audio-${m.id}" src="${audioSrc}" ontimeupdate="updateVoiceProgress(${m.id})" onended="resetVoicePlayer(${m.id})" onloadedmetadata="initVoiceDuration(${m.id})"></audio>
        </div>`;

        setTimeout(() => drawWaveformFromUrl(audioSrc, `v-canvas-${m.id}`, m.is_self ? 'rgba(255,255,255,0.7)' : '#64748b'), 50);
    } else if (m.image_path) {
        if (m.file_type === 'video') {
            const ss = m.image_path.replace(/'/g, "\\'");
            colHtml += '<div class="chat-video-wrapper" onclick="zoomChatVideo(\'' + ss + '\')" style="position:relative;cursor:pointer;border-radius:14px;overflow:hidden;max-width:280px;background:#000;margin-bottom:4px;">' +
                '<video src="' + m.image_path + '" style="width:100%;max-width:280px;display:block;border-radius:14px;" preload="metadata" muted playsinline></video>' +
                '<div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;">' +
                '<div style="width:48px;height:48px;background:rgba(0,0,0,0.55);border-radius:50%;display:flex;align-items:center;justify-content:center;">' +
                '<svg width="20" height="20" fill="white" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></div></div>' +
                '<span class="vid-dur" style="position:absolute;bottom:6px;right:8px;font-size:10px;font-weight:700;color:#fff;text-shadow:0 1px 4px rgba(0,0,0,0.8);pointer-events:none;"></span></div>';
        } else {
            const ss2 = m.image_path.replace(/'/g, "\\'");
            colHtml += `<div class="chat-image-wrap" onclick="zoomImage('${ss2}')">
                <img src="${m.image_path}" onload="scrollToBottom(true)">
            </div>`; 
        }
    }

    if (m.message) {
        colHtml += `<div style="${isCallMsg ? 'white-space:nowrap;' : ''}">${escapeHtml(m.message)}</div>`;
    }

    if (!m.is_system) {
        colHtml += `<div class="reaction-display-container" id="reactions-for-${m.id}" style="display:none;"></div>`;
    }
    colHtml += `</div>`; // .msg-bubble
    
    colHtml += `<div class="msg-meta" style="margin-top: 14px;">${m.created_at}</div>`;
    if (m.is_self) {
        colHtml += `<div class="seen-wrapper" id="seen-container-${m.id}"></div>`;
    }
    
    colHtml += `</div>`; // .msg-content-col

    row.innerHTML = avatarHtml + colHtml;
    row.setAttribute('data-is-self', m.is_self ? '1' : '0');
    row.setAttribute('data-status', m.status);
    box.appendChild(row);

    // Auto load gallery if active and media arrived
    if ((m.image_path || m.message_file) && document.getElementById('mediaGallery')?.classList.contains('active')) {
        loadMedia();
    }
}


function renderAllReactions() {
    // Group reactions by message_id
    const grouped = {};
    currentReactions.forEach(r => {
        if (!grouped[r.message_id]) grouped[r.message_id] = [];
        grouped[r.message_id].push(r);
    });

    document.querySelectorAll('.reaction-display-container').forEach(el => {
        const msgId = parseInt(el.id.replace('reactions-for-', ''));
        const rx = grouped[msgId];
        if (!rx || rx.length === 0) {
            el.style.display = 'none';
            return;
        }

        // Aggregate counts
        const counts = {};
        let myReaction = null; // We can't strictly know 'self' from reactions array right now unless comparing IDs, but the API doesn't specify nicely. We rely on sender_id.
        rx.forEach(r => {
            counts[r.reaction_type] = (counts[r.reaction_type] || 0) + 1;
        });

        const emojis = Object.keys(counts).map(k => REACTION_EMOJIS[k] || k).join('');
        const total = rx.length;
        
        let displayHtml = `<div class="reaction-display" title="${rx.map(x => x.reactor_name + ': ' + x.reaction_type).join(', ')}">
            <span>${emojis}</span>
            <span style="font-weight:700; opacity:0.8; margin-left:2px;">${total > 1 ? total : ''}</span>
        </div>`;
        
        el.innerHTML = displayHtml;
        el.style.display = 'block';
    });
}

function togglePicker(msgId, e) {
    if (e) e.stopPropagation();
    const picker = document.getElementById('picker-'+msgId);
    if (!picker) return;
    const isActive = picker.classList.contains('active');
    closeAllMenus();
    if (!isActive) {
        picker.classList.add('active');
        const row = document.getElementById(`msg-${msgId}`);
        if (row) row.classList.add('has-active-menu');
    }
}

function toggleMoreMenu(msgId, e) {
    if (e) e.stopPropagation();
    const menu = document.getElementById('more-'+msgId);
    if (!menu) return;
    const isActive = menu.classList.contains('active');
    closeAllMenus();
    if (!isActive) {
        menu.classList.add('active');
        const row = document.getElementById(`msg-${msgId}`);
        if (row) row.classList.add('has-active-menu');
    }
}

function closeAllMenus() {
    document.querySelectorAll('.reaction-picker').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.m-more-menu').forEach(m => m.classList.remove('active'));
    document.querySelectorAll('.msg-row').forEach(r => r.classList.remove('has-active-menu'));
}

document.addEventListener('click', () => closeAllMenus());

function pinMessage(msgId) {
    const bubble = document.getElementById('bubble-'+msgId);
    if (!bubble) return;
    
    // Check if already pinned
    if (bubble.querySelector('.pinned-badge')) {
        bubble.querySelector('.pinned-badge').remove();
    } else {
        const badge = document.createElement('div');
        badge.className = 'pinned-badge';
        badge.innerHTML = '<i class="bi bi-pin-fill"></i>';
        badge.title = 'Pinned Message';
        bubble.appendChild(badge);
    }
    // Note: Persistent pinning requires a backend API. This is a visual toggle.
    closeAllMenus();
}

var forwardMsgData = null;
var selectedForwardTargets = [];

function initForward(msgId, b64, hasImage) {
    forwardMsgData = { msgId, text: safeBase64Decode(b64), hasImage };
    openForwardModal();
    closeAllMenus();
}

function openForwardModal() {
    if (!document.getElementById('forwardModal')) {
        const div = document.createElement('div');
        div.id = 'forwardModal';
        div.className = 'order-details-modal-overlay';
        div.innerHTML = `
            <div class="order-details-modal-content" style="max-width:450px;">
                <div class="order-details-header">
                    <h2>Forward</h2>
                    <button class="order-details-close" onclick="closeForwardModal()">&times;</button>
                </div>
                <div style="padding:1rem; border-bottom:1px solid rgba(83,197,224,0.1);">
                    <div class="search-container">
                        <i class="bi bi-search search-icon"></i>
                        <input type="text" id="forwardSearch" placeholder="Search people..." oninput="loadForwardList(this.value)">
                    </div>
                </div>
                <!-- Preview -->
                <div style="padding:0.75rem 1rem; background:rgba(83,197,224,0.03); border-bottom:1px solid rgba(83,197,224,0.06);">
                    <div style="font-size:0.65rem; color:#4a7a8a; font-weight:800; text-transform:uppercase; margin-bottom:4px;">Preview</div>
                    <div id="forwardPreview" style="font-size:0.85rem; color:#eaf6fb; opacity:0.8; max-height:40px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"></div>
                </div>
                <div id="forwardList" style="padding:1rem; max-height:350px; overflow-y:auto; display:flex; flex-direction:column; gap:6px;"></div>
                <div style="padding:1rem; border-top:1px solid rgba(83,197,224,0.1); display:flex; justify-content:flex-end;">
                    <button id="forwardSendBtn" class="send-btn" style="width:auto; height:38px; padding:0 20px; font-weight:700; border-radius:12px;" onclick="processForward()" disabled>
                        Send <i class="bi bi-send-fill ml-2"></i>
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(div);
    }
    document.getElementById('forwardModal').style.display = 'flex';
    document.getElementById('forwardPreview').textContent = forwardMsgData.hasImage === '1' ? '📸 Attachment' : forwardMsgData.text;
    selectedForwardTargets = [];
    updateForwardBtn();
    loadForwardList();
}

function closeForwardModal() {
    document.getElementById('forwardModal').style.display = 'none';
    forwardMsgData = null;
}

function loadForwardList(q = '') {
    api(`/public/api/chat/list_conversations.php?archived=0&q=${encodeURIComponent(q)}`).then(data => {
        const list = document.getElementById('forwardList');
        if (!data.success || !data.conversations.length) {
            list.innerHTML = '<p class="p-8 text-center opacity-40 text-sm">No conversations found</p>';
            return;
        }
        list.innerHTML = data.conversations.map(c => {
            const isSelected = selectedForwardTargets.includes(c.order_id);
            return `
            <div onclick="toggleForwardTarget(${c.order_id})" style="padding:10px 14px; border-radius:14px; background:${isSelected ? 'rgba(83,197,224,0.15)' : 'rgba(255,255,255,0.03)'}; display:flex; align-items:center; gap:12px; cursor:pointer; transition:all 0.15s; border:1px solid ${isSelected ? 'rgba(83,197,224,0.3)' : 'transparent'};">
                <div style="width:36px; height:36px; border-radius:50%; background:#0a2530; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-weight:800; color:#53c5e0;">${(c.staff_name || 'T')[0]}</div>
                <div style="flex:1;">
                    <div style="font-size:0.85rem; font-weight:700; color:#eaf6fb;">${escapeHtml(c.staff_name || 'Team')}</div>
                    <div style="font-size:0.7rem; color:#4a7a8a;">${escapeHtml(c.service_name || 'Order')}</div>
                </div>
                <div style="width:20px; height:20px; border-radius:50%; border:2px solid ${isSelected ? '#53c5e0' : 'rgba(83,197,224,0.2)'}; background:${isSelected ? '#53c5e0' : 'transparent'}; display:flex; align-items:center; justify-content:center;">
                    ${isSelected ? '<i class="bi bi-check" style="color:#000; font-size:12px;"></i>' : ''}
                </div>
            </div>`;
        }).join('');
    });
}

function toggleForwardTarget(id) {
    if (selectedForwardTargets.includes(id)) {
        selectedForwardTargets = selectedForwardTargets.filter(x => x !== id);
    } else {
        selectedForwardTargets.push(id);
    }
    loadForwardList(document.getElementById('forwardSearch').value);
    updateForwardBtn();
}

function updateForwardBtn() {
    const btn = document.getElementById('forwardSendBtn');
    btn.disabled = selectedForwardTargets.length === 0;
    btn.innerHTML = `Send to ${selectedForwardTargets.length} <i class="bi bi-send-fill ml-2"></i>`;
}

async function processForward() {
    if (!forwardMsgData || !selectedForwardTargets.length) return;
    
    const btn = document.getElementById('forwardSendBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split animate-spin mr-2"></i> Forwarding...';

    let successCount = 0;
    for (const targetId of selectedForwardTargets) {
        const fd = new FormData();
        fd.append('order_id', targetId);
        fd.append('message', forwardMsgData.text);
        if (forwardMsgData.hasImage === '1') {
            // If it has image, we should ideally send the image path
            // But let's simplify for now as the user asked for "forward features like Messenger"
            // We'll just prefix it
            fd.append('message', '[Forwarded]: ' + forwardMsgData.text);
        } else {
            fd.append('message', forwardMsgData.text);
        }

        const res = await api('/public/api/chat/send_message.php', 'POST', fd);
        if (res.success) successCount++;
    }

    closeForwardModal();
    if (successCount > 0) {
        alert(`Successfully forwarded to ${successCount} conversation(s).`);
        loadConversations();
    }
}

function toggleReaction(msgId, reactionType) {
    const fd = new FormData();
    fd.append('message_id', msgId);
    fd.append('reaction_type', reactionType);
    api('/public/api/chat/react_message.php', 'POST', fd)
        .then(res => {
            if (res.success) {
                loadMessages(); 
                closeAllMenus();
            }
        });
}

function initReply(msgId, b64, hasImage) {
    const textPreview = safeBase64Decode(b64);
    replyToMessageId = msgId;
    document.getElementById('replyPreviewBox').style.display = 'flex';
    document.getElementById('replyPreviewText').textContent = hasImage === '1' ? '📸 Attachment' : textPreview;
    document.getElementById('chatInput').focus();
}

function cancelReply() {
    replyToMessageId = null;
    document.getElementById('replyPreviewBox').style.display = 'none';
}

let pendingVoiceBlob = null;

function sendMessage() {
    const input = document.getElementById('chatInput');
    const text = input.value.trim();
    
    if (text.length > 500) {
        alert("Message cannot exceed 500 characters.");
        return;
    }

    if (!text && !files.length && !pendingVoiceBlob) return;
    
    // If voice blob exists, call sendVoice helper instead of normal text send
    if (pendingVoiceBlob) {
        sendVoice();
        return;
    }

    const fd = new FormData();
    fd.append('order_id', activeOrderId);
    if (replyToMessageId) fd.append('reply_id', replyToMessageId);
    if (text) fd.append('message', text);
    files.forEach(f => fd.append('image[]', f));
    
    document.getElementById('chatInput').value = '';
    files = [];
    renderImgPreviews();
    cancelReply();
    
    api('/public/api/chat/send_message.php', 'POST', fd)
        .then(data => { if (data.success) { loadMessages(); } });
}

// --- Utilities ---

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function safeBase64Encode(str) {
    return btoa(unescape(encodeURIComponent(str || '')));
}

function safeBase64Decode(b64) {
    try {
        return decodeURIComponent(escape(atob(b64)));
    } catch(e) { return ''; }
}

function toggleArchive(id, state) {
    const fd = new FormData();
    fd.append('order_id', id);
    fd.append('archive', state ? 1 : 0);
    api('/public/api/chat/set_archived.php', 'POST', fd)
        .then(() => {
            if (activeOrderId === id && !viewingArchived) resetChat();
            loadConversations();
        });
}

function resetChat() {
    activeOrderId = null;
    window.uiOpenedChat = false;
    document.getElementById('chatWelcome').style.display = 'flex';
    document.getElementById('chatFooter').classList.remove('disabled');
    document.getElementById('chatInput').disabled = false;
    document.getElementById('chatInput').placeholder = 'Type a message...';

    // Show Call Buttons
    document.querySelectorAll('.call-btns').forEach(el => el.style.display = 'flex');
    initCallSystem();

    loadMessages();
    document.getElementById('activeName').textContent = 'Select a chat';
    document.getElementById('messageBox').innerHTML = '';
}

function toggleSidebar(open) {
    document.getElementById('sidebar').classList.toggle('open', open);
}

function scrollToBottom(smooth = true, force = false) {
    const box = document.getElementById('messageBox');
    if (!box) return;
    const threshold = 150;
    const isNearBottom = box.scrollHeight - box.scrollTop - box.clientHeight < threshold;
    if (force || isNearBottom) {
        box.scrollTo({ top: box.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
    }
}

function pinMessage(msgId) {
    const fd = new FormData();
    fd.append('message_id', msgId);
    api('/public/api/chat/pin_message.php', 'POST', fd).then(res => {
        if (res.success) {
            loadMessages();
            closeAllMenus();
        } else {
            alert(res.error || "Pin failed");
        }
    });
}

function zoomImage(src) {
    document.getElementById('chatLightboxImg').src = src;
    document.getElementById('chatLightboxImg').style.display = 'block';
    const vid = document.getElementById('chatLightboxVideo');
    if (vid) { vid.pause(); vid.src = ''; vid.style.display = 'none'; }
    document.getElementById('lightboxDownload').href = src;
    document.getElementById('chatLightbox').style.display = 'flex';
}

function zoomChatVideo(src) {
    document.getElementById('chatLightboxImg').style.display = 'none';
    document.getElementById('chatLightboxImg').src = '';
    const vid = document.getElementById('chatLightboxVideo');
    vid.src = src; vid.style.display = 'block';
    document.getElementById('lightboxDownload').href = src;
    document.getElementById('chatLightbox').style.display = 'flex';
    vid.play().catch(()=>{});
}

function closeChatLightbox() {
    document.getElementById('chatLightbox').style.display = 'none';
    const vid = document.getElementById('chatLightboxVideo');
    if (vid) { vid.pause(); vid.src = ''; }
}

function formatTimeAgo(d) {
    if (!d) return '...';
    const diff = (new Date() - new Date(d.replace(/-/g,'/'))) / 1000;
    if (diff < 60) return 'now';
    if (diff < 3600) return Math.floor(diff/60) + 'm';
    if (diff < 86400) return Math.floor(diff/3600) + 'h';
    return Math.floor(diff/86400) + 'd';
}

function escapeHtml(t) {
    const d = document.createElement('div');
    d.textContent = t || '';
    return d.innerHTML;
}

/* Custom Voice Player Functions */
function toggleVoicePlayer(id, src) {
    const audio = document.getElementById(`v-audio-${id}`);
    const icon = document.getElementById(`v-icon-${id}`);
    
    // Pause all other players first
    document.querySelectorAll('audio').forEach(a => {
        if (a.id !== `v-audio-${id}`) {
            a.pause();
            const sid = a.id.replace('v-audio-', '');
            const sicon = document.getElementById(`v-icon-${sid}`);
            if (sicon) {
                sicon.classList.remove('bi-pause-fill');
                sicon.classList.add('bi-play-fill');
            }
        }
    });

    if (audio.paused) {
        audio.play().catch(e => console.error("Play failed:", e));
        icon.classList.remove('bi-play-fill');
        icon.classList.add('bi-pause-fill');
    } else {
        audio.pause();
        icon.classList.remove('bi-pause-fill');
        icon.classList.add('bi-play-fill');
    }
}

function updateVoiceProgress(id) {
    const audio = document.getElementById(`v-audio-${id}`);
    const canvas = document.getElementById(`v-canvas-${id}`);
    const dur = document.getElementById(`v-dur-${id}`);
    if (!audio || !canvas) return;

    const percent = audio.currentTime / audio.duration;
    dur.textContent = formatAudioTime(audio.currentTime);

    // Redraw with progress highlight
    drawWaveformWithProgress(canvas, audio, percent);
}

const waveformCache = {};

async function drawWaveformFromUrl(url, canvasId, color) {
    if (waveformCache[url]) {
        drawRawToCanvas(canvasId, waveformCache[url], color);
        return;
    }
    try {
        const response = await fetch(url);
        const arrayBuffer = await response.arrayBuffer();
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const audioBuffer = await audioCtx.decodeAudioData(arrayBuffer);
        const rawData = audioBuffer.getChannelData(0); 
        const samples = 60; 
        const blockSize = Math.floor(rawData.length / samples);
        const filteredData = [];
        for (let i = 0; i < samples; i++) {
            let blockStart = blockSize * i;
            let sum = 0;
            for (let j = 0; j < blockSize; j++) {
                sum = sum + Math.abs(rawData[blockStart + j]);
            }
            filteredData.push(sum / blockSize);
        }
        const multiplier = Math.pow(Math.max(...filteredData), -1);
        const normalizedData = filteredData.map(n => n * multiplier);
        waveformCache[url] = normalizedData;
        drawRawToCanvas(canvasId, normalizedData, color);
        audioCtx.close();
    } catch(e) { console.error("Waveform error:", e); }
}

function drawRawToCanvas(canvasId, data, color, progress = 0) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const ctx = canvas.getContext("2d");
    const samples = data.length;
    const width = canvas.width / samples;
    
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    for (let i = 0; i < samples; i++) {
        const height = data[i] * canvas.height;
        const isPlayed = (i / samples) < progress;
        ctx.fillStyle = isPlayed ? '#0ea5e9' : color;
        ctx.fillRect(i * width, (canvas.height - height) / 2, width - 1, height);
    }
}

function drawWaveformWithProgress(canvas, audio, progress) {
    const url = audio.src;
    const data = waveformCache[url];
    if (!data) return;
    const isSelf = canvas.closest('.msg-row').classList.contains('self');
    drawRawToCanvas(canvas.id, data, isSelf ? 'rgba(255,255,255,0.7)' : '#64748b', progress);
}

function resetVoicePlayer(id) {
    const icon = document.getElementById(`v-icon-${id}`);
    const bar = document.getElementById(`v-progress-${id}`);
    const dur = document.getElementById(`v-dur-${id}`);
    const audio = document.getElementById(`v-audio-${id}`);
    
    if (icon) {
        icon.classList.remove('bi-pause-fill');
        icon.classList.add('bi-play-fill');
    }
    if (bar) bar.style.width = '0%';
    if (dur && audio) dur.textContent = formatAudioTime(audio.duration);
}

function initVoiceDuration(id) {
    const audio = document.getElementById(`v-audio-${id}`);
    const dur = document.getElementById(`v-dur-${id}`);
    if (audio && dur) dur.textContent = formatAudioTime(audio.duration);
}

function seekVoice(id, event) {
    const audio = document.getElementById(`v-audio-${id}`);
    if (!audio || !audio.duration) return;
    const container = event.currentTarget;
    const rect = container.getBoundingClientRect();
    const x = event.clientX - rect.left;
    const percent = x / rect.width;
    audio.currentTime = percent * audio.duration;
}

function formatAudioTime(seconds) {
    if (isNaN(seconds)) return '0:00';
    const min = Math.floor(seconds / 60);
    const sec = Math.floor(seconds % 60);
    return `${min}:${sec.toString().padStart(2, '0')}`;
}

function renderImgPreviews() {
    const area = document.getElementById('imgPreviews');
    area.style.display = files.length ? 'flex' : 'none';
    area.innerHTML = files.map((f, i) => {
        const url = URL.createObjectURL(f);
        return `<div class="relative"><img src="${url}" class="w-12 h-12 object-cover rounded-lg border border-white/20"><button onclick="files.splice(${i},1);renderImgPreviews()" class="absolute -top-2 -right-2 bg-red-500 text-white w-4 h-4 rounded-full text-[10px] flex items-center justify-center">×</button></div>`;
    }).join('');
}

// --- Typing / Status ---
let typingTimer = null;
function sendTypingStatus(isTyping) {
    if (!activeOrderId) return;
    const fd = new FormData();
    fd.append('order_id', activeOrderId);
    fd.append('is_typing', isTyping ? 1 : 0);
    fetch(window.baseUrl + '/public/api/chat/status.php', { method: 'POST', body: fd, credentials: 'same-origin' });
}

// --- Init ---

document.getElementById('chatInput').oninput = (e) => {
    const el = e.target;
    el.style.height = '1em';
    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
    
    const len = el.value.length;
    const cnt = document.getElementById('charCount');
    if (cnt) {
        cnt.textContent = `${len}/500`;
        cnt.classList.remove('limit-near', 'limit-reached');
        if (len >= 500) cnt.classList.add('limit-reached');
        else if (len >= 450) cnt.classList.add('limit-near');
    }
    
    sendTypingStatus(true);
    clearTimeout(typingTimer);
    typingTimer = setTimeout(() => sendTypingStatus(false), 2000);
};

document.getElementById('chatInput').onkeydown = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
};
document.getElementById('sendBtn').onclick = sendMessage;
document.getElementById('imgInput').onchange = function() {
    for (let f of this.files) {
        const isVideo = f.type.startsWith('video/');
        const maxMb = isVideo ? 50 : 10;
        if (f.size > maxMb * 1048576) { alert('"' + f.name + '" exceeds the ' + maxMb + 'MB limit.'); continue; }
        files.push(f);
    }
    renderImgPreviews();
    this.value = '';
};
document.getElementById('convSearch').oninput = () => {
    clearTimeout(listTimer);
    listTimer = setTimeout(loadConversations, 500);
};

function setupPoll() {
    clearInterval(pollTimer);
    if (activeOrderId) {
        pollTimer = setInterval(loadMessages, 3000); // 3 seconds for better real-time feel
    }
}

loadConversations();
setInterval(loadConversations, 10000);

function openOrderDetailsModal(id) {
    const modal = document.getElementById('orderDetailsModal');
    const loading = document.getElementById('orderDetailsLoading');
    const content = document.getElementById('orderDetailsContent');
    modal.style.display = 'flex';
    loading.style.display = 'block';
    content.style.display = 'none';
    document.body.style.overflow = 'hidden'; // Lock scroll
    
    api(`/public/api/chat/order_details.php?order_id=${id}`).then(data => {
        loading.style.display = 'none';
        if (!data.success) { content.innerHTML = '<div class="text-center py-10 text-red-400">Error loading order details.</div>'; content.style.display = 'block'; return; }

        
        const o = data.order;
        let html = `
            <div style="margin-bottom:1.5rem;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1.25rem;">
                    <div>
                        <div style="font-size:1.4rem; font-weight:900; color:#eaf6fb; line-height:1; display:flex; align-items:center; gap:10px;">
                            Order #${o.order_id} 
                            <span class="status-pill" style="background:rgba(83,197,224,0.12); color:#53c5e0; border:1px solid rgba(83,197,224,0.2); font-size:0.6rem; padding:3px 8px;">${o.status}</span>
                        </div>
                        <div style="font-size:0.75rem; color:#4a7a8a; margin-top:6px; font-weight:700;">
                            Placed on ${o.order_date} • <span style="color:${o.payment_status === 'Verified' ? '#10b981' : '#f59e0b'};">${o.payment_status || 'Unverified'}</span>
                        </div>
                    </div>
                    ${o.total_amount ? `
                    <div style="text-align:right;">
                        <div style="font-size:0.5rem; font-weight:900; color:#53c5e0; text-transform:uppercase; letter-spacing:0.15em; margin-bottom:2px;">Grand Total</div>
                        <div style="font-size:1.5rem; font-weight:900; color:#fff; letter-spacing:-0.02em;">${o.total_amount}</div>
                    </div>` : ''}
                </div>
                
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:0.75rem;">
                    ${o.notes ? `
                    <div class="notes-box" style="margin:0;">
                        <div class="pf-spec-key">Order Notes</div>
                        <div style="font-size:0.85rem; color:rgba(234,246,251,0.8); line-height:1.4;">${escapeHtml(o.notes)}</div>
                    </div>` : ''}
                    
                    ${o.revision_reason ? `
                    <div class="notes-box" style="margin:0; border-color:rgba(239,68,68,0.3); background:rgba(239,68,68,0.08);">
                        <div class="pf-spec-key" style="color:#ef4444;">Revision Requirement</div>
                        <div style="font-size:0.85rem; color:#fca5a5; line-height:1.4; font-weight:600;">${escapeHtml(o.revision_reason)}</div>
                    </div>` : ''}
                </div>
            </div>`;
        
        if (data.items) {
            html += `<div style="font-size:0.65rem; font-weight:900; color:#4a7a8a; text-transform:uppercase; letter-spacing:0.15em; margin-bottom:1rem; border-bottom:1px solid rgba(83,197,224,0.1); padding-bottom:6px;">Order Items (${data.items.length})</div>`;
            data.items.forEach(it => {
                const specs = it.customization || {};
                const entries = Object.entries(specs).filter(([k,v]) => v && v !== 'null' && typeof v !== 'object' && !['service_type', 'branch_id', 'design_file'].includes(k) && !k.toLowerCase().includes('notes'));


                
                html += `
                <div style="background:rgba(255,255,255,0.015); border:1px solid rgba(83,197,224,0.06); border-radius:0; padding:1rem; margin-bottom:0.75rem;">
                    <div style="display:flex; align-items:flex-start; gap:1.25rem; flex-wrap:nowrap;">
                        <div style="width:70px; height:70px; border-radius:0; background:rgba(0,10,20,0.3); backdrop-filter:blur(10px); border:1px solid rgba(83,197,224,0.1); overflow:hidden; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                            ${it.design_url ? `<img src="${it.design_url}" style="width:100%;height:100%;object-fit:cover;opacity:0.85;">` : `<div style="font-size:1.25rem; opacity:0.15;">📦</div>`}
                        </div>
                        <div style="flex:1;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2px;">
                                <div style="font-weight:900; font-size:1rem; color:#fff;">${it.service_name}</div>
                                ${it.subtotal ? `<div style="font-weight:900; color:#53c5e0; font-size:1rem;">${it.subtotal}</div>` : ''}
                            </div>
                            <div style="display:flex; align-items:center; gap:8px; margin-bottom:0.75rem;">
                                <div style="font-size:0.6rem; color:#53c5e0; font-weight:800; text-transform:uppercase; letter-spacing:0.04em;">${it.category}</div>
                                <div style="width:2px; height:2px; border-radius:50%; background:rgba(83,197,224,0.3);"></div>
                                <div style="font-size:0.75rem; color:#4a7a8a; font-weight:700;">Qty: <span style="color:#eaf6fb;">${it.quantity}</span></div>
                            </div>

                            
                            <div class="pf-spec-grid">
                                ${entries.map(([k,v]) => `
                                    <div class="pf-spec-box">
                                        <div class="pf-spec-key">${k.replace(/_/g,' ').replace('shirt ','')}</div>
                                        <div class="pf-spec-val">${escapeHtml(String(v))}</div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                </div>`;
            });
        }
        content.innerHTML = html;
        content.style.display = 'block';
        
        // Force sharp corners on all dynamic elements
        content.querySelectorAll('.pf-spec-box, .notes-box, .status-pill, div[style*="border-radius"]').forEach(el => {
            el.style.setProperty('border-radius', '0', 'important');
        });
    });
}

function closeOrderDetailsModal() { 
    document.getElementById('orderDetailsModal').style.display = 'none'; 
    document.body.style.overflow = ''; // Unlock scroll
}



// --- Media Gallery ---
let activeGalleryTab = 'image';
let sharedMedia = [];

function toggleMediaGallery(show) {
    const el = document.getElementById('mediaGallery');
    if (!el) return;
    if (show) {
        el.classList.add('active');
        loadMedia();
    } else {
        el.classList.remove('active');
    }
}

function switchGalleryTab(tab) {
    activeGalleryTab = tab;
    document.getElementById('gTabImages').classList.toggle('active', tab === 'image');
    document.getElementById('gTabVideos').classList.toggle('active', tab === 'video');
    renderMediaGrid();
}

async function loadMedia() {
    if (!activeOrderId) return;
    const grid = document.getElementById('mediaGrid');
    if (!grid) return;
    
    try {
        const response = await fetch(`${window.baseUrl}/public/api/chat/fetch_media.php?order_id=${activeOrderId}`);
        sharedMedia = await response.json();
        renderMediaGrid();
    } catch (e) {
        console.error("Gallery Error:", e);
        grid.innerHTML = '<div class="col-span-3 text-center py-10 text-red-400 text-xs">Error loading media</div>';
    }
}

function renderMediaGrid() {
    const grid = document.getElementById('mediaGrid');
    if (!grid) return;
    const filtered = sharedMedia.filter(m => m.file_type === activeGalleryTab);
    
    if (filtered.length === 0) {
        grid.innerHTML = `<div class="col-span-3 text-center py-16 opacity-30 text-[10px] uppercase font-black tracking-[0.2em]">Empty</div>`;
        return;
    }
    
    grid.innerHTML = filtered.map(m => {
        if (m.file_type === 'image') {
            return `<div class="gallery-item" onclick="zoomImage('${m.message_file.replace(/'/g, "\\'")}')">
                <img src="${m.message_file}" loading="lazy">
            </div>`;
        } else {
            return `<div class="gallery-item" onclick="zoomChatVideo('${m.message_file.replace(/'/g, "\\'")}')">
                <video src="${m.message_file}#t=0.1" preload="metadata" muted></video>
                <div class="vid-icon"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></div>
            </div>`;
        }
    }).join('');
}

function updateCustomerSeenIndicators(lastSeenId) {
    document.querySelectorAll('.seen-indicator').forEach(el => el.remove());
    if (!partnerAvatarUrl || lastSeenId === -1) return;
    
    const container = document.getElementById(`seen-container-${lastSeenId}`);
    if (container) {
        container.innerHTML = `<div class="seen-indicator" style="background-image: url('${partnerAvatarUrl}')" title="Seen"></div>`;
    }
}

// --- Voice Logic (Customer Updated with Waveform) ---
let mediaRecorder;
let audioChunks = [];
let timerInterval;
const MAX_DURATION = 180; // 3 mins

let audioCtx;
let analyser;
let source;
let animationId;
let previewAudio;
let previewAnimationId;

function startVoiceVisualizer(stream) {
    audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    analyser = audioCtx.createAnalyser();
    source = audioCtx.createMediaStreamSource(stream);
    source.connect(analyser);
    analyser.fftSize = 256;

    const bufferLength = analyser.frequencyBinCount;
    const dataArray = new Uint8Array(bufferLength);
    const canvas = document.getElementById("recordingCanvas");
    const ctx = canvas.getContext("2d");

    function draw() {
        animationId = requestAnimationFrame(draw);
        analyser.getByteFrequencyData(dataArray);

        ctx.clearRect(0, 0, canvas.width, canvas.height);
        const barWidth = (canvas.width / bufferLength) * 2.5;
        let x = 0;

        for (let i = 0; i < bufferLength; i++) {
            const barHeight = (dataArray[i] / 255) * canvas.height;
            ctx.fillStyle = '#ef4444';
            ctx.fillRect(x, canvas.height - barHeight, barWidth, barHeight);
            x += barWidth + 1;
        }
    }
    draw();
}

function stopVoiceVisualizer() {
    if (animationId) cancelAnimationFrame(animationId);
    if (audioCtx) audioCtx.close();
}

async function drawStaticWaveform(blob, canvasId, color = '#64748b') {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const ctx = canvas.getContext("2d");
    const arrayBuffer = await blob.arrayBuffer();
    const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    const audioBuffer = await audioCtx.decodeAudioData(arrayBuffer);
    const rawData = audioBuffer.getChannelData(0); 
    const samples = 70; 
    const blockSize = Math.floor(rawData.length / samples);
    const filteredData = [];
    for (let i = 0; i < samples; i++) {
        let blockStart = blockSize * i;
        let sum = 0;
        for (let j = 0; j < blockSize; j++) {
            sum = sum + Math.abs(rawData[blockStart + j]);
        }
        filteredData.push(sum / blockSize);
    }
    const multiplier = Math.pow(Math.max(...filteredData), -1);
    const normalizedData = filteredData.map(n => n * multiplier);

    ctx.clearRect(0, 0, canvas.width, canvas.height);
    const width = canvas.width / samples;
    for (let i = 0; i < samples; i++) {
        const height = normalizedData[i] * canvas.height;
        ctx.fillStyle = color;
        ctx.fillRect(i * width, (canvas.height - height) / 2, width - 1, height);
    }
    audioCtx.close();
}

const startRec = document.getElementById("startRecord");
const stopRec = document.getElementById("stopRecord");
const recStatus = document.getElementById("recordStatus");
const timerDisp = document.getElementById("timer");
const inpCont = document.getElementById("inputContainer");
const cancelRec = document.getElementById("cancelRecord");

function stopRecording() {
    if (mediaRecorder && mediaRecorder.state === "recording") {
        mediaRecorder.stop();
        mediaRecorder.stream.getTracks().forEach(track => track.stop());
    }
    stopVoiceVisualizer();
    clearInterval(timerInterval);
    recStatus.classList.add("hidden");
    stopRec.classList.add("hidden");
    cancelRec.classList.add("hidden");
    startRec.classList.remove("recording");
    inpCont.classList.remove("hidden");
}

function showVoicePreview() {
    pendingVoiceBlob = new Blob(audioChunks, { type: 'audio/webm' });
    if (pendingVoiceBlob.size === 0) {
        pendingVoiceBlob = null;
        return;
    }
    document.getElementById("voicePreviewArea").style.display = 'flex';
    document.getElementById("inputContainer").classList.add("hidden");
    document.getElementById("cancelRecord").classList.remove("hidden");
    
    // Draw visualizer for preview
    drawStaticWaveform(pendingVoiceBlob, 'previewWaveformCanvas', '#0a2530');
    
    // Get duration
    const tempAudio = new Audio(URL.createObjectURL(pendingVoiceBlob));
    tempAudio.onloadedmetadata = () => {
        const m = Math.floor(tempAudio.duration / 60);
        const s = Math.floor(tempAudio.duration % 60);
        document.getElementById("previewDuration").textContent = `${m}:${s.toString().padStart(2, '0')}`;
    };
}

function togglePreviewPlayback() {
    if (!pendingVoiceBlob) return;
    const icon = document.getElementById("previewPlayIcon");
    
    if (!previewAudio) {
        previewAudio = new Audio(URL.createObjectURL(pendingVoiceBlob));
        previewAudio.onended = () => {
            icon.classList.replace('bi-pause-fill', 'bi-play-fill');
            previewAudio = null;
        };
    }

    if (previewAudio.paused) {
        previewAudio.play();
        icon.classList.replace('bi-play-fill', 'bi-pause-fill');
    } else {
        previewAudio.pause();
        icon.classList.replace('bi-pause-fill', 'bi-play-fill');
    }
}

function clearVoicePreview() {
    if (previewAudio) { previewAudio.pause(); previewAudio = null; }
    pendingVoiceBlob = null;
    audioChunks = [];
    document.getElementById("voicePreviewArea").style.display = 'none';
    document.getElementById("inputContainer").classList.remove("hidden");
    document.getElementById("cancelRecord").classList.add("hidden");
    document.getElementById("stopRecord").classList.add("hidden");
    document.getElementById("recordStatus").classList.add("hidden");
    document.getElementById("startRecord").classList.remove("recording");
    const icon = document.getElementById("previewPlayIcon");
    if (icon) icon.classList.replace('bi-pause-fill', 'bi-play-fill');
}

function sendVoice() {
    if (!pendingVoiceBlob) return;

    const fd = new FormData();
    fd.append("voice", pendingVoiceBlob);
    fd.append("order_id", activeOrderId);
    if (replyToMessageId) fd.append('reply_id', replyToMessageId);

    const btn = document.getElementById('sendBtn');
    const oldIcon = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<i class='bi bi-hourglass-split animate-spin' style='font-size:1.2rem;'></i>`;

    api('/public/api/chat/send_voice.php', 'POST', fd)
        .then(data => {
            if (data.success) {
                clearVoicePreview();
                cancelReply();
                loadMessages();
            } else {
                alert(data.error || "Voice upload failed");
            }
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = oldIcon;
            timerDisp.textContent = '0:00';
        });
}

if (startRec) {
    startRec.onclick = async () => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            mediaRecorder = new MediaRecorder(stream);
            mediaRecorder.start();

            startVoiceVisualizer(stream);

            audioChunks = [];
            let seconds = 0;

            recStatus.classList.remove("hidden");
            stopRec.classList.remove("hidden");
            cancelRec.classList.remove("hidden");
            startRec.classList.add("recording");
            inpCont.classList.add("hidden");

            timerInterval = setInterval(() => {
                seconds++;
                let min = Math.floor(seconds / 60);
                let sec = seconds % 60;
                timerDisp.textContent = `${min}:${sec.toString().padStart(2, '0')}`;

                if (seconds >= MAX_DURATION) {
                    stopRecording();
                }
            }, 1000);

            mediaRecorder.ondataavailable = e => { audioChunks.push(e.data); };
            mediaRecorder.onstop = showVoicePreview;
        } catch (err) {
            console.error("Mic access denied:", err);
            alert("Microphone access is required for voice recording.");
        }
    };
}

if (stopRec) stopRec.onclick = () => stopRecording();

if (cancelRec) {
    cancelRec.onclick = () => {
        if (mediaRecorder && mediaRecorder.state === "recording") {
            mediaRecorder.onstop = null; // Don't show preview
            mediaRecorder.stop();
            mediaRecorder.stream.getTracks().forEach(track => track.stop());
        }
        clearVoicePreview();
    };
}

</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
