(function(){
/* QR */
var n=25,seed=20261016;function r(){seed=(seed*1664525+1013904223)>>>0;return seed/4294967296}
var g=[],i,j;for(i=0;i<n;i++){g.push([]);for(j=0;j<n;j++)g[i].push(r()>0.52)}
function finder(x,y){for(var a=0;a<7;a++)for(var b=0;b<7;b++){var on=a===0||a===6||b===0||b===6||(a>=2&&a<=4&&b>=2&&b<=4);g[y+a][x+b]=on}
 for(a=-1;a<8;a++){[[x+a,y-1],[x+a,y+7],[x-1,y+a],[x+7,y+a]].forEach(function(p){if(p[0]>=0&&p[0]<n&&p[1]>=0&&p[1]<n)g[p[1]][p[0]]=false})}}
finder(0,0);finder(n-7,0);finder(0,n-7);
var s='<svg viewBox="0 0 '+n+' '+n+'" width="100%" height="100%" role="img" aria-label="Sample QR code, decorative" shape-rendering="crispEdges">';
for(i=0;i<n;i++)for(j=0;j<n;j++)if(g[i][j])s+='<rect x="'+j+'" y="'+i+'" width="1" height="1" fill="#1b2127"/>';
document.getElementById('qr').innerHTML=s+'</svg>';
/* grace: the length comes from the Manager's setting (data-grace) */
var gp=document.getElementById('grace-panel');
if(gp){
var G=parseInt(gp.getAttribute('data-grace'),10)||0,span=Math.max(G*2,G+10,10);
var gs=document.getElementById('gs'),gt=document.getElementById('gt'),gm=document.getElementById('gm'),gr=document.getElementById('gr'),gb=document.getElementById('gb');
function clock(o){var t=8*60+o,h=Math.floor(t/60),m=t%60,ap=h>=12?'PM':'AM',h12=h%12||12;return h12+':'+(m<10?'0':'')+m+' '+ap}
gs.max=span;gs.step=span>60?5:1;gs.value=0;
if(gb)gb.style.width=(G/span*100)+'%';
document.getElementById('gl1').textContent=G>0?clock(G)+' grace ends':'No grace';
document.getElementById('gl2').textContent=clock(span);
function gc(){var o=+gs.value;gt.textContent=clock(o);gm.style.left=(o/span*100)+'%';
 if(o===0)gr.innerHTML='<div class="alert" style="border-left-color:var(--ok);background:var(--ok-bg)"><b>On time.</b> Show your QR code at the booth.</div>';
 else if(o<=G)gr.innerHTML='<div class="alert"><b>Inside the grace period.</b> Your bay is still held and the code still works. '+(G-o)+' min left.</div>';
 else gr.innerHTML='<div class="alert stop"><b>Reservation expired.</b> The code stops working, the bay is released and you get a notification. You can reserve again for free.</div>'}
gs.addEventListener('input',gc);gc();
}
/* fees: rates come from the Manager's rate set (window.BAYLINE_RATES) */
var fp=document.getElementById('fees-panel');
if(fp){
var dm=document.getElementById('dm'),dsr=document.getElementById('dsr'),dout=document.getElementById('dout'),dd=document.getElementById('dd');
var pct=(parseFloat(fp.getAttribute('data-discount'))||0)/100;
function dc(){var m=+dm.value;dd.textContent=dur(m);dout.innerHTML=feeHTML(fee(m,dsr&&dsr.checked?pct:0,false,false),'Senior or PWD discount')}
dm.addEventListener('input',dc);if(dsr)dsr.addEventListener('input',dc);dc();
}
})();
