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
/* grace */
var gs=document.getElementById('gs'),gt=document.getElementById('gt'),gm=document.getElementById('gm'),gr=document.getElementById('gr');
function gc(){var o=+gs.value;gt.textContent='8:'+(o<10?'0':'')+o+' AM';gm.style.left=(o/20*100)+'%';
 if(o===0)gr.innerHTML='<div class="alert" style="border-left-color:var(--ok);background:var(--ok-bg)"><b>On time.</b> Show your QR code at the booth.</div>';
 else if(o<=10)gr.innerHTML='<div class="alert"><b>Inside the grace period.</b> Your bay is still held and the code still works. '+(10-o)+' min left.</div>';
 else gr.innerHTML='<div class="alert stop"><b>Reservation expired.</b> The code stops working, bay A-06 is released and you get a notification. You can reserve again for free.</div>'}
gs.addEventListener('input',gc);gc();
/* fees */
var dm=document.getElementById('dm'),dsr=document.getElementById('dsr'),dout=document.getElementById('dout'),dd=document.getElementById('dd');
function dc(){var m=+dm.value;dd.textContent=dur(m);dout.innerHTML=feeHTML(fee(m,dsr.checked?0.2:0,false,false),'Senior or PWD discount, 20%')}
dm.addEventListener('input',dc);dsr.addEventListener('input',dc);dc();
})();
