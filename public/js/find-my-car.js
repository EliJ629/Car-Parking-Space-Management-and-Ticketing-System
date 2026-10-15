(function(){
/* find my car */
var D={'PK-0017':{slot:'A-05',st:'parked',veh:'Black SUV, LMN \u2022\u202234',ent:'Today, 5:20 PM',par:'50 min',fee:'\u20B130.00 so far'},
'PK-0018':{slot:'A-07',st:'pending',veh:'White van, BNM \u2022\u202228',ent:'Today, 5:28 PM',par:'34 min',fee:'Paid \u20B130.00, receipt RC-0014'},
'PK-0011':{st:'left',left:'4:48 PM'}};
function where(sl){var z=sl[0],k=+sl.slice(2),o=['first','second','third','fourth'][(k-1)%4];
 if(z==='A'&&k<=4)return 'Zone A, back row, '+o+' bay from the left.';
 if(z==='A')return 'Zone A, front row beside the entrance lane, '+o+' bay from the left.';
 return 'Zone B, below the entrance lane, '+o+' bay from the left.'}
var ft=document.getElementById('ft'),fr=document.getElementById('fr'),ff=document.getElementById('ff');
if(!ft||!fr||!ff)return;
function row(k,v){return '<div class="row"><span>'+k+'</span><span>'+v+'</span></div>'}
function look(){
  var v=ft.value.trim().toUpperCase();ft.value=v;
  if(!/^PK-\d{4}$/.test(v)){fr.innerHTML='<div class="alert stop"><b>Check the number.</b> Enter PK, a dash and 4 digits, for example PK-0017.</div>';ft.setAttribute('aria-invalid','true');return}
  ft.removeAttribute('aria-invalid');
  var d=D[v];
  if(!d){fr.innerHTML='<div class="alert stop"><b>No active ticket matches '+v+'.</b> Check the number on your ticket, or ask the booth attendant to search by plate.</div>';return}
  if(d.st==='left'){fr.innerHTML='<div class="panel" style="background:var(--surface)"><span class="badge b-done">Exited</span><p style="font-weight:700;margin-top:8px">'+v+' is complete</p><p style="font-size:14px;color:var(--muted)">This vehicle left the lot at '+d.left+' today. The ticket is closed.</p></div>';return}
  var badge=d.st==='parked'?'<span class="badge b-ok">Parked</span>':'<span class="badge b-pend">Pending exit</span>';
  var note=d.st==='parked'?'<div class="alert" style="margin-top:12px"><b>Pay cash at the booth first.</b> After payment the guard approves your exit at the gate.</div>':'<div class="alert" style="margin-top:12px"><b>Drive to the exit.</b> The guard checks your plate and taps Good to Go.</div>';
  fr.innerHTML='<div class="panel" style="background:var(--surface);padding:16px"><div style="display:flex;gap:14px;align-items:center"><div class="plate"><small>ZONE '+d.slot[0]+'</small><b>'+d.slot+'</b></div><div><p style="font-weight:700;line-height:1.3">Your car is in slot '+d.slot+'</p><p style="font-size:13.5px;color:var(--muted)">'+where(d.slot)+'</p><div style="margin-top:6px">'+badge+'</div></div></div><div class="lot plain" id="fm" style="margin-top:14px"></div><div style="margin-top:10px">'+row('Ticket',v)+row('Vehicle',d.veh)+row('Entered',d.ent)+row('Parked',d.par)+row('Fee',d.fee)+'</div>'+note+'</div>';
  var m={};LOT.forEach(function(x){m[x[0]]='plain'});LBL.plain='';
  renderLot(document.getElementById('fm'),m,d.slot);
  document.querySelector('#fm .leg').innerHTML='<span><i class="p"></i>Your car</span><span><i></i>Other bays</span>';
}
ff.addEventListener('submit',function(e){e.preventDefault();look()});
var fc=document.getElementById('fchips');
if(fc)fc.addEventListener('click',function(e){var b=e.target.closest('[data-t]');if(b){ft.value=b.dataset.t;look()}});
if(ft.value.trim())look();
})();
