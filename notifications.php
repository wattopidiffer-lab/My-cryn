<?php
require_once __DIR__ . '/functions.php';
$user = fn_getCurrentUser();
if (!$user) {
    header('Location: /index.php'); exit;
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Уведомления | My Cryndel</title>
    <link rel="stylesheet" href="/">
    <style>
        body { font-family: Inter, sans-serif; padding: 24px; background: #f7fafc; }
        .wrap { max-width: 900px; margin: 0 auto; }
        .card { background:#fff; border-radius:12px; padding:16px; box-shadow: 0 6px 18px rgba(0,0,0,0.06); }
        .notif { display:flex; gap:12px; padding:12px; border-bottom:1px solid #f1f5f9; }
        .notif:last-child { border-bottom: none; }
        .notif .icon { width:36px; height:36px; border-radius:8px; background:#eef2ff; display:flex; align-items:center; justify-content:center; }
        .notif .text { flex:1; }
        .notif.unread { background: linear-gradient(90deg,#fffef8,#f0fdf4); }
        .actions { margin-top:12px; display:flex; gap:8px; }
        .btn { padding:8px 12px; border-radius:8px; border:none; cursor:pointer; }
        .btn-primary { background:#10b981; color:#fff }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Уведомления</h1>
    <div class="card" id="notifCard">
        <div id="notifList">Загрузка...</div>
        <div class="actions">
            <button class="btn btn-primary" id="markAll">Отметить все прочитанными</button>
        </div>
    </div>
</div>
<script>
function escapeHtml(s){ const d=document.createElement('div'); d.textContent = s; return d.innerHTML; }
function load(){ fetch('/api.php?action=get_notifications').then(r=>r.json()).then(data=>{
    const list = document.getElementById('notifList'); const nots = data.notifications||[];
    if(!nots.length){ list.innerHTML = '<div style="padding:12px;color:#6b7280">У вас нет уведомлений</div>'; return; }
    list.innerHTML = nots.map(n=>{
        const cls = n.read? '':'unread';
        const t = (n.data && n.data.from_username)? ('@'+escapeHtml(n.data.from_username)+' ') : '';
        const msg = n.type==='item_like'? t+'поставил(а) лайк' : (n.type==='item_comment'? t+'прокомментировал(а)' : JSON.stringify(n.data));
        return `<div class="notif ${cls}" data-id="${n.id}"><div class="icon"><i class="fas fa-bell"></i></div><div class="text"><div>${msg}</div><div style="font-size:12px;color:#9ca3af">${escapeHtml(n.created_at||'')}</div></div><div style="display:flex;align-items:center"><button class="btn" onclick="mark('${n.id}')">Отметить</button></div></div>`;
    }).join('');
}).catch(e=>{ document.getElementById('notifList').textContent = 'Ошибка загрузки'; console.error(e); }); }
function mark(id){ const fd=new FormData(); fd.append('action','mark_read'); fd.append('notification_id', id); fetch('/api.php',{method:'POST',body:fd}).then(()=>load()); }
document.getElementById('markAll').addEventListener('click', function(){ const fd=new FormData(); fd.append('action','mark_all_read'); fetch('/api.php',{method:'POST',body:fd}).then(()=>load()); });
load();
</script>
</body>
</html>