(function(){
/* exit demo */
var s=0,lot=document.getElementById('xlot'),badge=document.getElementById('xbadge'),st=document.getElementById('xst'),b1=document.getElementById('xb1'),b2=document.getElementById('xb2'),b3=document.getElementById('xb3');
function line(k,v){return '<div class="row"><span>'+k+'</span><span>'+v+'</span></div>'}
function draw(){
  var m={};LOT.forEach(function(x){m[x[0]]=x[1]});
  m['A-07']=['occ','pend','av'][s];
  renderLot(lot,m,'A-07');
  badge.className='badge '+['b-done','b-pend','b-ok'][s];badge.textContent=['Occupied','Pending exit','Available'][s];
  var rows=[
   ['Booth screen: PK-0018 owes \u20B130.00 for 34 min','Gate screen: nothing waiting','Slot A-07: Occupied'],
   ['Booth screen: paid \u20B130.00, receipt RC-0014','Gate screen: 1 waiting, BNM 6628 white van, A-07','Slot A-07: held, cannot be given to another car'],
   ['Booth screen: session closed','Gate screen: approved, queue empty','Slot A-07: Available again']][s];
  st.innerHTML=line('Booth',rows[0].split(': ')[1])+line('Gate',rows[1].split(': ')[1])+line('Slot A-07',rows[2].split(': ')[1]);
  b1.disabled=s!==0;b2.disabled=s!==1;
}
b1.addEventListener('click',function(){s=1;draw()});b2.addEventListener('click',function(){s=2;draw()});b3.addEventListener('click',function(){s=0;draw()});
draw();
/* tabs */
var T=[
{k:'Parking Manager',tag:'Sets the rules and watches the lot.',tools:[['Dashboard','Sales, slot status and open alerts in one view.'],['Slots','Add, edit and take bays out of service.'],['Rates and policies','Hourly rates, discounts, penalties and the 12-hour limit.'],['Accounts and logs','Create staff accounts, reset passwords and read the activity log.']],tiles:[['12','Slots'],['5','Available'],['1','Pending exit'],['2','Alerts']],first:'Dashboard'},
{k:'Booth Attendant',tag:'Runs entry, tickets and cash at the booth.',tools:[['New entry','Walk-in details and a slot pick, or a reservation scan. The ticket prints.'],['Exit and payment','Fee breakdown, discounts, cash and receipt.'],['Parking slots','Live board with logged manual corrections.'],['Records','Today by default, searchable by ticket or plate, plus the shift summary.']],tiles:[['4','Vehicles in lot'],['1','Pending exits'],['5','Available slots'],['2','Reservations']],first:'Home'},
{k:'Security Guard',tag:'Checks cars at the gate and reports incidents.',tools:[['Gate validation','Paid cars in a queue. Good to Go, or search by plate or ticket.'],['Incident reports','Suspicious vehicles, damage, violations, mismatches and unpaid exits.'],['Long-parked reports','Automatic alerts after the limit, or a manual report by plate.'],['Records','Parking records and every incident, filtered by date, type and status.']],tiles:[['1','Waiting for approval'],['11','Approved today'],['1','Long-parking alert'],['12 h','Long-parking limit']],first:'Gate validation'}];
var tabs=document.getElementById('tabs'),panel=document.getElementById('tabpanel'),cur=0;
function esc(x){return x}
function render(){
  tabs.innerHTML=T.map(function(t,i){return '<button role="tab" type="button" class="chip" id="t'+i+'" aria-selected="'+(i===cur)+'" aria-controls="tabpanel" data-i="'+i+'" style="'+(i===cur?'background:var(--ink);color:var(--surface);border-color:var(--ink)':'')+';padding:9px 18px;font-size:14px">'+t.k+'</button>'}).join('');
  var t=T[cur];
  panel.setAttribute('aria-labelledby','t'+cur);
  panel.innerHTML='<div><h3 style="font-size:40px">'+t.k+'</h3><p style="color:var(--muted);margin-top:6px">'+t.tag+'</p><div style="margin-top:14px">'+t.tools.map(function(x){return '<div class="row" style="display:block"><span style="color:var(--ink);font-weight:700;display:block">'+x[0]+'</span><span style="font-weight:400;color:var(--muted);display:block;text-align:left">'+x[1]+'</span></div>'}).join('')+'</div></div>'+
  '<div class="panel" style="background:var(--out-bg)"><p class="eyebrow">First screen: '+t.first+'</p><div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:12px">'+t.tiles.map(function(x){return '<div style="background:var(--surface);border:1px solid var(--line);border-radius:8px;padding:14px"><div class="big" style="font-size:48px">'+x[0]+'</div><div style="font-size:13px;color:var(--muted);margin-top:4px">'+x[1]+'</div></div>'}).join('')+'</div><p style="font-size:12px;color:var(--muted);margin-top:12px">Sample figures for Thursday, Oct 15, 2026, 6:10 PM.</p></div>';
}
tabs.addEventListener('click',function(e){var b=e.target.closest('[data-i]');if(b){cur=+b.dataset.i;render();document.getElementById('t'+cur).focus()}});
render();
/* fees */
var fm=document.getElementById('fmin'),fd=document.getElementById('fdisc'),fl=document.getElementById('flost'),fo=document.getElementById('fover'),fout=document.getElementById('fout'),fdur=document.getElementById('fdur');
function calc(){var m=+fm.value,d=+fd.value;fdur.textContent=dur(m);fout.innerHTML=feeHTML(fee(m,d,fl.checked,fo.checked),fd.options[fd.selectedIndex].text)}
[fm,fd,fl,fo].forEach(function(x){x.addEventListener('input',calc)});calc();
})();
