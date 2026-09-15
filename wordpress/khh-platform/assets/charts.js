/* Biểu đồ SVG dùng chung — vẽ theo đúng thang đo, màu lấy từ token giao diện */
(function(){
'use strict';
var A=window.APP,esc=A.esc;

/* vòng tròn: segs = [{v,c,l}] */
A.donut=function(segs,centerNum,centerLab,size){
  size=size||168;
  var total=segs.reduce(function(s,x){return s+(x.v||0)},0);
  var r=size/2-14,cx=size/2,cy=size/2,C=2*Math.PI*r,off=0,h='';
  if(!total)h='<circle cx="'+cx+'" cy="'+cy+'" r="'+r+'" fill="none" stroke="var(--panel-3)" stroke-width="16"/>';
  else segs.forEach(function(s){
    if(!s.v)return;
    var len=s.v/total*C;
    h+='<circle cx="'+cx+'" cy="'+cy+'" r="'+r+'" fill="none" stroke="'+s.c+'" stroke-width="16"'+
       ' stroke-dasharray="'+len.toFixed(2)+' '+(C-len).toFixed(2)+'" stroke-dashoffset="'+(-off).toFixed(2)+'"'+
       ' transform="rotate(-90 '+cx+' '+cy+')"/>';
    off+=len});
  return '<div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap">'+
    '<svg width="'+size+'" height="'+size+'" viewBox="0 0 '+size+' '+size+'" role="img" aria-label="'+esc(centerLab||'')+'">'+h+
    '<text x="'+cx+'" y="'+(cy-2)+'" text-anchor="middle" fill="var(--ink)" font-size="22" font-weight="500">'+esc(String(centerNum))+'</text>'+
    '<text x="'+cx+'" y="'+(cy+16)+'" text-anchor="middle" fill="var(--muted)" font-size="10" letter-spacing="1">'+esc(String(centerLab||'').toUpperCase())+'</text>'+
    '</svg><div style="display:grid;gap:6px;font-size:12px">'+segs.map(function(s){
      return '<div><span class="dot" style="background:'+s.c+'"></span>'+esc(s.l)+' <b class="num">'+s.v+'</b></div>'}).join('')+
    '</div></div>'};

/* đồng hồ cung tròn 0-100 */
A.gauge=function(p,color,big,lab,size){
  size=size||96;p=Math.max(0,Math.min(100,p||0));
  var r=size/2-8,cx=size/2,cy=size/2,C=2*Math.PI*r,len=p/100*C;
  return '<div style="display:flex;gap:10px;align-items:center">'+
    '<svg width="'+size+'" height="'+size+'" viewBox="0 0 '+size+' '+size+'" role="img" aria-label="'+esc(lab||'')+' '+p+'%">'+
    '<circle cx="'+cx+'" cy="'+cy+'" r="'+r+'" fill="none" stroke="var(--panel-3)" stroke-width="9"/>'+
    '<circle cx="'+cx+'" cy="'+cy+'" r="'+r+'" fill="none" stroke="'+color+'" stroke-width="9"'+
      ' stroke-dasharray="'+len.toFixed(2)+' '+(C-len).toFixed(2)+'" stroke-linecap="round" transform="rotate(-90 '+cx+' '+cy+')"/>'+
    '<text x="'+cx+'" y="'+(cy+4)+'" text-anchor="middle" fill="'+color+'" font-size="14" font-weight="500">'+p.toFixed(1)+'%</text></svg>'+
    '<div><div style="font-size:26px;font-weight:500;color:'+color+'" class="num">'+esc(String(big))+'</div>'+
    '<div style="font-size:11px;color:var(--muted);max-width:150px">'+esc(lab||'')+'</div></div></div>'};

/* đường gấp khúc: series=[{name,color,pts:[n]}], labels=[] */
A.line=function(series,labels,h){
  h=h||190;var w=Math.max(320,labels.length*54),pad={l:44,r:12,t:12,b:26};
  var max=0;series.forEach(function(s){s.pts.forEach(function(v){if(v>max)max=v})});
  max=max||1;var niceMax=Math.ceil(max/4)*4||4;
  var iw=w-pad.l-pad.r,ih=h-pad.t-pad.b;
  function X(i){return pad.l+(labels.length<2?iw/2:i/(labels.length-1)*iw)}
  function Y(v){return pad.t+ih-(v/niceMax*ih)}
  var g='';
  for(var k=0;k<=4;k++){var y=pad.t+ih-k/4*ih;
    g+='<line x1="'+pad.l+'" y1="'+y.toFixed(1)+'" x2="'+(w-pad.r)+'" y2="'+y.toFixed(1)+'" stroke="var(--line)" stroke-width="1"/>'+
       '<text x="'+(pad.l-6)+'" y="'+(y+3.5).toFixed(1)+'" text-anchor="end" fill="var(--muted)" font-size="9">'+Math.round(k/4*niceMax)+'</text>'}
  series.forEach(function(s){
    var d=s.pts.map(function(v,i){return (i?'L':'M')+X(i).toFixed(1)+' '+Y(v).toFixed(1)}).join(' ');
    g+='<path d="'+d+'" fill="none" stroke="'+s.color+'" stroke-width="2" stroke-linejoin="round"/>';
    s.pts.forEach(function(v,i){g+='<circle cx="'+X(i).toFixed(1)+'" cy="'+Y(v).toFixed(1)+'" r="2.6" fill="'+s.color+'"/>'})});
  labels.forEach(function(l,i){
    g+='<text x="'+X(i).toFixed(1)+'" y="'+(h-8)+'" text-anchor="middle" fill="var(--muted)" font-size="9">'+esc(l)+'</text>'});
  return '<div style="overflow-x:auto"><svg width="'+w+'" height="'+h+'" viewBox="0 0 '+w+' '+h+'" role="img">'+g+'</svg></div>'+
    '<div style="display:flex;gap:14px;flex-wrap:wrap;font-size:11.5px;margin-top:6px">'+series.map(function(s){
      return '<span><span class="dot" style="background:'+s.color+'"></span>'+esc(s.name)+'</span>'}).join('')+'</div>'};

/* phễu chuyển đổi: stages=[{name,v}] */
A.funnel=function(stages){
  if(!stages.length)return '<p class="empty">Chưa có dữ liệu.</p>';
  var w=260,h=34*stages.length+16,max=stages[0].v||1,g='';
  stages.forEach(function(s,i){
    var bw=Math.max(26,(s.v/max)*w),x=(w-bw)/2,y=8+i*34;
    g+='<rect x="'+x.toFixed(1)+'" y="'+y+'" width="'+bw.toFixed(1)+'" height="26" fill="var(--app)" opacity="'+(1-i*0.13).toFixed(2)+'" rx="2"/>'+
       '<text x="'+(w/2)+'" y="'+(y+17)+'" text-anchor="middle" fill="#fff" font-size="11" font-weight="500">'+s.v+'</text>'});
  return '<div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap">'+
    '<svg width="'+w+'" height="'+h+'" viewBox="0 0 '+w+' '+h+'" role="img">'+g+'</svg>'+
    '<div style="display:grid;gap:9px;font-size:12px">'+stages.map(function(s,i){
      return '<div><span class="dot" style="background:var(--app);opacity:'+(1-i*0.13).toFixed(2)+'"></span>'+esc(s.name)+
        ' <b class="num">'+s.v+'</b></div>'}).join('')+'</div></div>'};

/* cột dọc: bars=[{l,v,c}] */
A.bars=function(bars,h){
  h=h||150;var bw=34,gap=14,w=Math.max(260,bars.length*(bw+gap)+20),pad={t:14,b:26};
  var max=bars.reduce(function(m,b){return Math.max(m,b.v)},0)||1,g='';
  bars.forEach(function(b,i){
    var bh=(b.v/max)*(h-pad.t-pad.b),x=12+i*(bw+gap),y=h-pad.b-bh;
    g+='<rect x="'+x+'" y="'+y.toFixed(1)+'" width="'+bw+'" height="'+Math.max(1,bh).toFixed(1)+'" rx="2" fill="'+(b.c||'var(--app)')+'"/>'+
       '<text x="'+(x+bw/2)+'" y="'+(y-4).toFixed(1)+'" text-anchor="middle" fill="var(--ink-2)" font-size="10">'+b.v+'</text>'+
       '<text x="'+(x+bw/2)+'" y="'+(h-8)+'" text-anchor="middle" fill="var(--muted)" font-size="9.5">'+esc(b.l)+'</text>'});
  return '<div style="overflow-x:auto"><svg width="'+w+'" height="'+h+'" viewBox="0 0 '+w+' '+h+'" role="img">'+g+'</svg></div>'};

/* thanh ngang nhiều lớp */
A.wbar=function(parts,total){
  total=total||parts.reduce(function(s,p){return s+p.v},0)||1;
  return '<div class="wbar">'+parts.map(function(p){
    return '<i style="width:'+(p.v/total*100)+'%;background:'+p.c+'"></i>'}).join('')+'</div>'};

A.tile=function(lb,vl,ft,color){
  return '<div class="tile"><div class="lb">'+esc(lb)+'</div>'+
    '<div class="vl" style="'+(color?'color:'+color:'')+'">'+vl+'</div>'+
    (ft?'<div class="ft">'+ft+'</div>':'')+'</div>'};
})();
