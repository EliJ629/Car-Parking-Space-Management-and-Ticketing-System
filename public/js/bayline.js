(function(){
var root=document.documentElement,tb=document.getElementById('theme'),KEY='bayline-theme';
function eff(){var t=root.getAttribute('data-theme');if(t)return t;return matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'}
function lab(){if(!tb)return;var t=eff()==='dark'?'Switch to light mode':'Switch to dark mode';tb.setAttribute('title',t);if(!tb.textContent.trim())tb.setAttribute('aria-label',t)}
try{var saved=localStorage.getItem(KEY);if(saved==='dark'||saved==='light')root.setAttribute('data-theme',saved)}catch(e){}
if(tb){tb.addEventListener('click',function(){var n=eff()==='dark'?'light':'dark';root.setAttribute('data-theme',n);try{localStorage.setItem(KEY,n)}catch(e){}lab()});lab()}
/* Every password field gets an eye button that shows or hides what was typed */
var EYE='<svg class="on" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>'
 +'<svg class="off" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9.9 5.2A10 10 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.1 6.6C3.6 8.3 2 12 2 12s3.6 7 10 7a9.6 9.6 0 0 0 4.2-1M9.9 9.9a3 3 0 0 0 4.2 4.2"/><path d="M3 3l18 18"/></svg>';
document.querySelectorAll('input[type=password]').forEach(function(input){
  if(input.closest('.pw'))return;
  var wrap=document.createElement('div');wrap.className='pw';
  input.parentNode.insertBefore(wrap,input);wrap.appendChild(input);
  var b=document.createElement('button');
  b.type='button';b.className='pw-eye';b.innerHTML=EYE;
  b.setAttribute('aria-pressed','false');b.setAttribute('aria-label','Show password');b.setAttribute('title','Show password');
  if(input.id)b.setAttribute('aria-controls',input.id);
  b.addEventListener('click',function(){
    var show=input.type==='password';
    input.type=show?'text':'password';
    b.setAttribute('aria-pressed',show?'true':'false');
    var t=show?'Hide password':'Show password';
    b.setAttribute('aria-label',t);b.setAttribute('title',t);
    input.focus({preventScroll:true});
  });
  wrap.appendChild(b);
});
/* Disable the submit button while a form is sending, so a double tap does not send it twice */
document.querySelectorAll('form[data-busy]').forEach(function(form){
  form.addEventListener('submit',function(){
    var b=form.querySelector('button[type=submit]');
    if(b){setTimeout(function(){b.disabled=true;b.textContent=b.getAttribute('data-busy-label')||'Please wait'},0)}
  });
});
})();
window.LOT=[['A-01','av'],['A-02','occ'],['A-03','av'],['A-04','res'],['A-05','occ'],['A-06','av'],['A-07','pend'],['A-08','av'],['B-01','occ'],['B-02','av'],['B-03','res'],['B-04','out']];
window.LBL={av:'Available',occ:'Occupied',res:'Reserved',pend:'Pending exit',out:'Out of service'};
window.renderLot=function(el,st,me){
  function cell(c){var s=st[c]||'av';return '<div class="sl '+s+(c===me?' me':'')+'"><b>'+c+'</b><span>'+LBL[s]+'</span></div>'}
  var A=['A-01','A-02','A-03','A-04'],A2=['A-05','A-06','A-07','A-08'],B=['B-01','B-02','B-03','B-04'];
  el.innerHTML='<div class="zl">ZONE A</div><div class="g">'+A.map(cell).join('')+'</div><div class="g">'+A2.map(cell).join('')+'</div><div class="lane"><span class="en">ENTRANCE</span><span class="ds"></span><span class="ex">EXIT</span></div><div class="zl">ZONE B</div><div class="g">'+B.map(cell).join('')+'</div><div class="leg"><span><i></i>Available</span><span><i class="r"></i>Reserved</span><span><i class="o"></i>Occupied</span><span><i class="p"></i>Pending exit</span></div>';
};
window.fee=function(min,disc,lost,over){
  var hrs=Math.max(1,Math.ceil(min/60)),first=30,extra=(hrs-1)*20,sub=first+extra,cap=false;
  if(sub>300){sub=300;cap=true}
  var d=Math.round(sub*disc*100)/100,pen=(lost?100:0)+(over?150:0);
  return {hrs:hrs,first:first,extra:extra,addHrs:hrs-1,sub:sub,cap:cap,disc:d,pen:pen,lost:lost,over:over,total:sub-d+pen};
};
window.money=function(n){return '\u20B1'+n.toFixed(2)};
window.dur=function(m){var h=Math.floor(m/60),r=m%60;return h?(h+' h '+(r<10?'0':'')+r+' min'):(r+' min')};
window.feeHTML=function(f,discLabel){
  var h='<div class="row"><span>First hour</span><span>'+money(f.first)+'</span></div>';
  h+='<div class="row"><span>Additional hours ('+f.addHrs+' x \u20B120)</span><span>'+money(f.extra)+'</span></div>';
  if(f.cap)h+='<div class="row"><span>Daily maximum applied</span><span>'+money(f.sub)+'</span></div>';
  if(f.disc)h+='<div class="row"><span>'+discLabel+'</span><span>-'+money(f.disc)+'</span></div>';
  if(f.lost)h+='<div class="row"><span>Lost ticket penalty</span><span>'+money(100)+'</span></div>';
  if(f.over)h+='<div class="row"><span>Overstay penalty</span><span>'+money(150)+'</span></div>';
  h+='<div class="row" style="border-top:2px solid var(--ink);font-size:18px"><span style="color:var(--ink);font-weight:700">Amount payable</span><span style="font-family:var(--f-d);font-size:30px;line-height:1">'+money(f.total)+'</span></div>';
  return h;
};
