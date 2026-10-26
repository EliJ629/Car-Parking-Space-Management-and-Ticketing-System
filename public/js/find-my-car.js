(function(){
/* Find my car: asks the server about the ticket. The answer holds the bay, a masked plate and the fee so far, never the full plate. */
var box=document.getElementById('find');
var ft=document.getElementById('ft'),fr=document.getElementById('fr'),ff=document.getElementById('ff'),go=document.getElementById('fgo');
if(!box||!ft||!fr||!ff)return;
var url=box.getAttribute('data-find-url');
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
function row(k,v){return '<div class="row"><span>'+esc(k)+'</span><span>'+esc(v)+'</span></div>'}
function alertBox(cls,html){fr.innerHTML='<div class="alert'+(cls?' '+cls:'')+'">'+html+'</div>'}
function normalize(v){
  var t=String(v).toUpperCase().replace(/[\s_.]+/g,'');
  var m=t.match(/^(?:PK-?)?(\d{1,4})$/);
  if(!m)return null;
  var n=parseInt(m[1],10);
  return n>=1&&n<=9999?'PK-'+('0000'+n).slice(-4):null;
}
var busy=false;
function look(){
  if(busy)return;
  var t=normalize(ft.value);
  if(!t){ft.setAttribute('aria-invalid','true');alertBox('stop','<b>Check the number.</b> Enter the ticket number like PK-0017: PK and up to four digits.');return}
  ft.removeAttribute('aria-invalid');ft.value=t;
  busy=true;if(go){go.disabled=true}
  alertBox('','Looking for '+esc(t)+'...');
  fetch(url+'?ticket='+encodeURIComponent(t),{headers:{'Accept':'application/json'},credentials:'same-origin'})
    .then(function(r){if(r.status===429){throw new Error('rate')}return r.json().then(function(d){return {s:r.status,d:d}})})
    .then(function(x){show(x.d,t)})
    .catch(function(e){alertBox('stop',e&&e.message==='rate'?'<b>Too many tries.</b> Wait a minute and try again.':'<b>Could not check the ticket.</b> Check your connection and try again.')})
    .then(function(){busy=false;if(go){go.disabled=false}});
}
function show(d,t){
  if(d.state==='invalid'){alertBox('stop','<b>Check the number.</b> '+esc(d.message||''));return}
  if(d.state==='unknown'){alertBox('stop','<b>No ticket matches '+esc(t)+'.</b> Check the number on your ticket, or ask the booth attendant to search by plate.');return}
  if(d.state==='left'){fr.innerHTML='<div class="panel" style="background:var(--surface)"><span class="badge b-done">Exited</span><p style="font-weight:700;margin-top:8px">'+esc(t)+' is complete</p><p style="font-size:14px;color:var(--muted)">This vehicle left the lot'+(d.left_at?' at '+esc(d.left_at):'')+'. The ticket is closed.</p></div>';return}
  var parked=d.state==='parked';
  var badge=parked?'<span class="badge b-ok">Parked</span>':'<span class="badge b-pend">Pending exit</span>';
  var note=parked?'<div class="alert" style="margin-top:12px"><b>Pay cash at the booth first.</b> After payment the guard approves your exit at the gate.</div>':'<div class="alert" style="margin-top:12px"><b>Drive to the exit.</b> The guard checks your plate and taps Good to Go.</div>';
  var zone=d.zone?String(d.zone).toUpperCase():'';
  fr.innerHTML='<div class="panel" style="background:var(--surface);padding:16px"><div style="display:flex;gap:14px;align-items:center"><div class="plate"><small>'+esc(zone)+'</small><b>'+esc(d.slot)+'</b></div><div><p style="font-weight:700;line-height:1.3">Your car is in slot '+esc(d.slot)+'</p>'+(d.zone?'<p style="font-size:13.5px;color:var(--muted)">'+esc(d.zone)+'</p>':'')+'<div style="margin-top:6px">'+badge+'</div></div></div><div style="margin-top:12px">'+row('Ticket',t)+row('Vehicle',(d.vehicle||'')+(d.plate?', '+d.plate:''))+row('Entered',d.entered)+row('Parked',d.parked_for)+row('Fee',d.fee)+'</div>'+note+'</div>';
}
ff.addEventListener('submit',function(e){e.preventDefault();look()});
})();
