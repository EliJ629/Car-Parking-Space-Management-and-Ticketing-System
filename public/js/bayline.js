(function(){
var root=document.documentElement,tb=document.getElementById('theme'),KEY='bayline-theme';
function eff(){var t=root.getAttribute('data-theme');if(t)return t;return matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'}
function lab(){if(!tb)return;var t=eff()==='dark'?'Switch to light mode':'Switch to dark mode';tb.setAttribute('title',t);if(!tb.textContent.trim())tb.setAttribute('aria-label',t)}
/* The chosen theme is kept in localStorage and in a cookie. The cookie is shared by every port of the same address, and it is the
   fallback when the browser clears or blocks localStorage. Other open tabs follow the change. */
function readSaved(){var t=null;try{t=localStorage.getItem(KEY)}catch(e){}if(t!=='dark'&&t!=='light'){var m=document.cookie.match(/(?:^|; )bayline-theme=(dark|light)/);t=m?m[1]:null}return t==='dark'||t==='light'?t:null}
function save(t){try{localStorage.setItem(KEY,t)}catch(e){}try{document.cookie=KEY+'='+t+'; path=/; max-age=31536000; SameSite=Lax'}catch(e){}}
function apply(t){if(t)root.setAttribute('data-theme',t);lab()}
var saved=readSaved();if(saved)save(saved);apply(saved);
if(tb){tb.addEventListener('click',function(){var n=eff()==='dark'?'light':'dark';save(n);apply(n)})}
window.addEventListener('storage',function(e){if(e.key===KEY&&(e.newValue==='dark'||e.newValue==='light'))apply(e.newValue)});
window.addEventListener('pageshow',function(e){if(e.persisted)apply(readSaved())});
document.querySelectorAll('[data-open-dialog]').forEach(function(button){
  button.addEventListener('click',function(){
    var dialog=document.getElementById(button.getAttribute('data-open-dialog'));
    if(dialog&&typeof dialog.showModal==='function'&&!dialog.open)dialog.showModal();
  });
});
document.querySelectorAll('.manager-dialog[data-open-on-load]').forEach(function(dialog){
  if(typeof dialog.showModal==='function'&&!dialog.open)dialog.showModal();
});
document.querySelectorAll('.manager-dialog,.vdlg').forEach(function(dialog){
  dialog.addEventListener('click',function(event){
    if(event.target===dialog)dialog.close();
  });
  dialog.querySelectorAll('[data-close-dialog]').forEach(function(button){
    button.addEventListener('click',function(){dialog.close()});
  });
});
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

/* Password rules tick off while typing: <input data-pwcheck="#rules" data-pwconfirm="#confirm" data-pwmatch="#match"> */
document.querySelectorAll('input[data-pwcheck]').forEach(function(input){
  var list=document.querySelector(input.getAttribute('data-pwcheck'));
  var confirm=input.getAttribute('data-pwconfirm')?document.querySelector(input.getAttribute('data-pwconfirm')):null;
  var match=input.getAttribute('data-pwmatch')?document.querySelector(input.getAttribute('data-pwmatch')):null;
  if(!list)return;
  var tests={len:function(v){return v.length>=10},num:function(v){return /[0-9]/.test(v)},upper:function(v){return /[A-Z]/.test(v)},special:function(v){return /[^A-Za-z0-9\s]/.test(v)}};
  function run(){
    var v=input.value;
    list.querySelectorAll('[data-rule]').forEach(function(li){
      var ok=tests[li.getAttribute('data-rule')](v);
      li.classList.toggle('ok',ok);
      var s=li.querySelector('.sr');if(s)s.textContent=ok?' (done)':' (not yet)';
    });
    if(confirm&&match){
      if(!confirm.value){match.textContent='';match.className='hint'}
      else if(confirm.value===v){match.textContent='Passwords match.';match.className='hint okline'}
      else{match.textContent='Passwords do not match yet.';match.className='hint badline'}
    }
  }
  input.addEventListener('input',run);if(confirm)confirm.addEventListener('input',run);run();
});
/* Phone fields take digits and a leading + only. Letters, spaces and dashes are removed as they are typed or pasted. */
document.querySelectorAll('input[type=tel]').forEach(function(input){
  input.maxLength=13;
  function clean(){
    var v=input.value.replace(/[^0-9+]/g,'');
    v=(v.charAt(0)==='+'?'+':'')+v.replace(/\+/g,'');
    v=v.slice(0,13);
    if(v!==input.value)input.value=v;
  }
  input.addEventListener('input',clean);input.addEventListener('change',clean);clean();
});
/* Gmail address check while typing: <input data-live-gmail data-live-target="#email-note"> */
document.querySelectorAll('input[data-live-gmail]').forEach(function(input){
  var note=document.querySelector(input.getAttribute('data-live-target'));
  var re=/^(?=[^@]{6,30}@)[a-z0-9]+(?:\.[a-z0-9]+)*@gmail\.com$/;
  var url=input.getAttribute('data-check-url'),timer=null,seq=0;
  function plain(){note.textContent='We send a confirmation link to this Gmail address.';note.className='hint'}
  function run(){
    var v=input.value.trim().toLowerCase();
    if(!note)return;
    clearTimeout(timer);seq++;
    if(!v){plain();return}
    if(!re.test(v)){note.textContent='Use a Gmail address, for example maria.santos@gmail.com.';note.className='hint badline';return}
    plain();
    if(!url||typeof fetch!=='function')return;
    var mine=seq;
    timer=setTimeout(function(){
      fetch(url+'?email='+encodeURIComponent(v),{headers:{'Accept':'application/json'},credentials:'same-origin'})
        .then(function(r){return r.ok?r.json():null})
        .then(function(d){
          if(mine!==seq||!d)return;
          if(d.taken){note.textContent='This Gmail account is already registered. Sign in instead.';note.className='hint badline'}
        }).catch(function(){});
    },400);
  }
  input.addEventListener('input',run);run();
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
