(function(){
var root=document.documentElement,tb=document.getElementById('theme'),KEY='bayline-theme';
function eff(){var t=root.getAttribute('data-theme');if(t)return t;return matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'}
function lab(){if(!tb)return;var t=eff()==='dark'?'Switch to light mode':'Switch to dark mode';tb.setAttribute('title',t);if(!tb.textContent.trim())tb.setAttribute('aria-label',t)}
try{var saved=localStorage.getItem(KEY);if(saved==='dark'||saved==='light')root.setAttribute('data-theme',saved)}catch(e){}
if(tb){tb.addEventListener('click',function(){var n=eff()==='dark'?'light':'dark';root.setAttribute('data-theme',n);try{localStorage.setItem(KEY,n)}catch(e){}lab()});lab()}
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
/* Gmail address check while typing: <input data-live-gmail data-live-target="#email-note"> */
document.querySelectorAll('input[data-live-gmail]').forEach(function(input){
  var note=document.querySelector(input.getAttribute('data-live-target'));
  var re=/^(?=[^@]{6,30}@)[a-z0-9]+(?:\.[a-z0-9]+)*@gmail\.com$/;
  function run(){
    var v=input.value.trim().toLowerCase();
    if(!note)return;
    if(!v){note.textContent='We send a 6 digit code to this Gmail address.';note.className='hint';return}
    if(re.test(v)){note.textContent='Looks like a Gmail address.';note.className='hint okline'}
    else{note.textContent='Use a Gmail address, for example maria.santos@gmail.com.';note.className='hint badline'}
  }
  input.addEventListener('input',run);run();
});
/* Filter panels update themselves: <form data-auto-filter>.
   Selects and dates submit straight away; typed searches wait briefly so each key does not send a request.
   Paired date inputs marked data-range-start / data-range-end validate the range before submitting. */
document.querySelectorAll('form[data-auto-filter]').forEach(function(form){
  var timer=null;
  var starts=[].slice.call(form.querySelectorAll('[data-range-start]'));
  var ends=[].slice.call(form.querySelectorAll('[data-range-end]'));

  function checkRange(show){
    var ok=true;
    starts.forEach(function(start,index){
      var end=ends[index];
      if(!end)return;
      if(start.value){end.min=start.value}else{end.removeAttribute('min')}
      var message='';
      if(start.value&&end.value&&end.value<start.value)message='The end date cannot be before the start date.';
      end.setCustomValidity(message);
      if(message){ok=false;if(show)end.reportValidity()}
    });
    return ok;
  }

  function submit(){
    if(timer){clearTimeout(timer);timer=null}
    if(!checkRange(true))return;
    var page=form.querySelector('input[name=page]');if(page)page.value='1';
    if(typeof form.requestSubmit==='function')form.requestSubmit();else form.submit();
  }

  starts.concat(ends).forEach(function(input){
    input.addEventListener('change',function(){checkRange(false);submit()});
  });

  form.addEventListener('change',function(event){
    var field=event.target;
    if(!field.matches('select,input[type=date],input[type=checkbox],input[type=radio]'))return;
    if(field.hasAttribute('data-range-start')||field.hasAttribute('data-range-end'))return;
    submit();
  });

  form.addEventListener('input',function(event){
    var field=event.target;
    if(field.tagName!=='INPUT'||field.type==='hidden')return;
    if(field.matches('[type=date],[type=checkbox],[type=radio]'))return;
    if(timer)clearTimeout(timer);
    timer=setTimeout(submit,450);
  });

  form.addEventListener('keydown',function(event){
    if(event.key!=='Enter'||event.target.tagName!=='INPUT')return;
    if(event.target.type==='hidden')return;
    event.preventDefault();submit();
  });

  form.addEventListener('submit',function(event){
    if(!checkRange(true))event.preventDefault();
  });

  checkRange(false);
});
/* A form that only needs a start/end check: <form data-range-check data-range-strict>.
   Strict means the end must be later than the start, not merely the same day. */
document.querySelectorAll('form[data-range-check]').forEach(function(form){
  var starts=[].slice.call(form.querySelectorAll('[data-range-start]'));
  var ends=[].slice.call(form.querySelectorAll('[data-range-end]'));
  var strict=form.hasAttribute('data-range-strict');

  function check(show){
    var ok=true;
    starts.forEach(function(start,index){
      var end=ends[index];
      if(!end)return;
      if(!end.hasAttribute('data-base-min'))end.setAttribute('data-base-min',end.getAttribute('min')||'');
      var base=end.getAttribute('data-base-min');
      if(start.value){end.min=start.value}else if(base){end.min=base}else{end.removeAttribute('min')}
      var message='';
      if(start.value&&end.value){
        if(end.value<start.value)message='The end time cannot be before the start time.';
        else if(strict&&end.value===start.value)message='The end time must be later than the start time.';
      }
      end.setCustomValidity(message);
      if(message){ok=false;if(show)end.reportValidity()}
    });

    return ok;
  }

  starts.concat(ends).forEach(function(input){
    input.addEventListener('input',function(){check(false)});
    input.addEventListener('change',function(){check(false)});
  });
  form.addEventListener('submit',function(event){if(!check(true))event.preventDefault()});
  check(false);
});

document.querySelectorAll('[data-layout-canvas]').forEach(function(canvas){
  var editor=canvas.closest('[data-layout-editor]'),panel=editor&&editor.closest('.manager-lot-panel'),slots=[].slice.call(canvas.querySelectorAll('[data-layout-slot]')),labels=[].slice.call(canvas.querySelectorAll('[data-layout-zone-label]'));
  var actions=panel&&panel.querySelector('[data-layout-actions]'),edit=actions&&actions.querySelector('[data-layout-edit]');
  var auto=actions&&actions.querySelector('[data-layout-auto]'),cancel=actions&&actions.querySelector('[data-layout-cancel]');
  var save=actions&&actions.querySelector('[data-layout-save]'),form=panel&&panel.querySelector('[data-layout-form]');
  var inputs=form&&form.querySelector('[data-layout-inputs]'),editing=false,snapshot={slots:[],labels:[]};
  function clamp(value,min,max){return Math.max(min,Math.min(max,value))}
  function arrange(){
    var groups={},zones,height=40,offset=20;
    slots.forEach(function(slot){(groups[slot.dataset.zone]||(groups[slot.dataset.zone]=[])).push(slot)});
    zones=Object.keys(groups);
    zones.forEach(function(zone){height+=Math.ceil(groups[zone].length/8)*72+28});
    height=Math.max(520,height);canvas.style.height=height+'px';
    labels.forEach(function(label){
      var labelX=parseFloat(label.dataset.x),labelY=parseFloat(label.dataset.y);
      label.style.left=(clamp(Number.isFinite(labelX)?labelX:0.04,0,.9)*100)+'%';
      label.style.top=(clamp(Number.isFinite(labelY)?labelY:offset/height,0,.95)*100)+'%';
    });
    zones.forEach(function(zone){
      groups[zone].forEach(function(slot,index){
        var x=slot.dataset.x===''||slot.dataset.x===undefined?0.04+index%8*.115:parseFloat(slot.dataset.x);
        var y=slot.dataset.y===''||slot.dataset.y===undefined?(offset+20+Math.floor(index/8)*72)/height:parseFloat(slot.dataset.y);
        slot.style.left=clamp(x,0,.9)*100+'%';slot.style.top=clamp(y,0,.88)*100+'%';
      });
      var label=labels.find(function(item){return item.dataset.zone===zone});
      if(label&&label.dataset.x===''&&label.dataset.y===''){label.style.left='4%';label.style.top=(offset/height*100)+'%'}
      offset+=Math.ceil(groups[zone].length/8)*72+28;
    });
  }
  function editingState(value){
    editing=value;if(!actions)return;
    edit.hidden=value;auto.hidden=!value;cancel.hidden=!value;save.hidden=!value;
    actions.classList.toggle('is-editing',value);editor.classList.toggle('is-editing',value);
  }
  arrange();
  if(editor){
    window.requestAnimationFrame(function(){
      editor.scrollLeft=Math.max(0,(editor.scrollWidth-editor.clientWidth)/2);
    });
  }
  if(!editor)return;
  edit.addEventListener('click',function(){
    snapshot.slots=slots.map(function(slot){return {slot:slot,left:slot.style.left,top:slot.style.top}});
    snapshot.labels=labels.map(function(label){return {label:label,left:label.style.left,top:label.style.top}});
    editingState(true);
  });
  cancel.addEventListener('click',function(){
    snapshot.slots.forEach(function(item){item.slot.style.left=item.left;item.slot.style.top=item.top});
    snapshot.labels.forEach(function(item){item.label.style.left=item.left;item.label.style.top=item.top});
    editingState(false);
  });
  auto.addEventListener('click',function(){
    slots.forEach(function(slot){slot.dataset.x='';slot.dataset.y=''});
    labels.forEach(function(label){label.dataset.x='';label.dataset.y=''});
    arrange();
  });
  save.addEventListener('click',function(){
    inputs.innerHTML='';var base=canvas.getBoundingClientRect();
    slots.forEach(function(slot){
      var rect=slot.getBoundingClientRect(),position={id:slot.dataset.slotId,x:clamp((rect.left-base.left)/base.width,0,.9),y:clamp((rect.top-base.top)/base.height,0,.88)};
      Object.keys(position).forEach(function(key){var input=document.createElement('input');input.type='hidden';input.name='positions['+slot.dataset.slotId+']['+key+']';input.value=position[key];inputs.appendChild(input)});
    });
    labels.forEach(function(label){
      var rect=label.getBoundingClientRect(),position={zone:label.dataset.zone,x:clamp((rect.left-base.left)/base.width,0,.9),y:clamp((rect.top-base.top)/base.height,0,.95)};
      Object.keys(position).forEach(function(key){var input=document.createElement('input');input.type='hidden';input.name='zone_positions['+label.dataset.zone+']['+key+']';input.value=position[key];inputs.appendChild(input)});
    });
    form.submit();
  });
  slots.forEach(function(slot){
    var start=null;
    slot.addEventListener('pointerdown',function(event){
      if(!editing)return;event.preventDefault();slot.setPointerCapture(event.pointerId);
      var base=canvas.getBoundingClientRect(),rect=slot.getBoundingClientRect();
      start={x:event.clientX,y:event.clientY,left:rect.left-base.left,top:rect.top-base.top};
    });
    slot.addEventListener('pointermove',function(event){
      if(!start)return;var base=canvas.getBoundingClientRect();
      slot.style.left=clamp(start.left+event.clientX-start.x,0,base.width-slot.offsetWidth)/base.width*100+'%';
      slot.style.top=clamp(start.top+event.clientY-start.y,0,base.height-slot.offsetHeight)/base.height*100+'%';
    });
    slot.addEventListener('pointerup',function(){start=null});
    slot.addEventListener('click',function(event){if(editing)event.preventDefault()});
  });
  labels.forEach(function(label){
    var start=null;
    label.addEventListener('pointerdown',function(event){
      if(!editing)return;
      event.preventDefault();label.setPointerCapture(event.pointerId);
      var base=canvas.getBoundingClientRect(),rect=label.getBoundingClientRect();
      start={x:event.clientX,y:event.clientY,left:rect.left-base.left,top:rect.top-base.top};
    });
    label.addEventListener('pointermove',function(event){
      if(!start)return;
      var base=canvas.getBoundingClientRect();
      label.style.left=clamp(start.left+event.clientX-start.x,0,base.width-label.offsetWidth)/base.width*100+'%';
      label.style.top=clamp(start.top+event.clientY-start.y,0,base.height-label.offsetHeight)/base.height*100+'%';
    });
    label.addEventListener('pointerup',function(){start=null});
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
