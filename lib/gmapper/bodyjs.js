
/* ###VERSIONSBLOCKINLCUDE### */

var gmarkers=[];
var htmls=[];
var punkt_lat=[];
var punkt_lng=[];
var punkt_ini_zoom=[];
var i=0;
function openwin(id){
gmarkers[id].openInfoWindowHtml(htmls[id]);
}
function drawCircle(_2,_3,_4,_5,_6,_7){
var _8=map.getZoom();
var _9=map.fromLatLngToDivPixel(_2);
var _a=_9;
rpt1=_a.x+_3;
rpt2=_a.y+_3;
_a=new GPoint(rpt1,rpt2);
var _b=Array();
with(Math){
_3=floor(sqrt(pow((_9.x-_a.x),2)+pow((_9.y-_a.y),2)));
for(var a=0;a<361;a+=10){
var _d=a*(PI/180);
var y=_9.y+_3*sin(_d);
var x=_9.x+_3*cos(_d);
var p=new GPoint(x,y);
var _11=map.fromDivPixelToLatLng(p,_8);
_b.push(_11);
if(_6!=""){
var _12=createMapMarker(_11,_6);
map.addOverlay(_12);
}
}
if(_7!=""){
circleLine=new GPolygon(_b,_4,_5,0.8,_7,0.5);
}else{
circleLine=new GPolygon(_b,_4,_5,0.8);
}
map.addOverlay(circleLine);
}
}
function createMarker(_13,_14){
var _15=new GIcon();
_15.image=_14[0];
_15.iconSize=new GSize(_14[1],_14[2]);
subt=_14[2]/100*10;
subt=Math.ceil(subt);
var _16=_14[2]-subt;
_15.iconAnchor=new GPoint(10,_16);
var _17=new GMarker(_13,_15);
return _17;
}
function createMapMarker(_18,_19){
var _1a=new GIcon();
_1a.image=_19[0];
_1a.iconSize=new GSize(_19[1],_19[2]);
subt=_19[2]/100*10;
subt=Math.ceil(subt);
var _1b=_19[2]-subt;
_1a.iconAnchor=new GPoint(10,_1b);
var _1c=_19[2]/3;
_1c=Math.floor(_1c);
_1a.infoWindowAnchor=new GPoint(_1c,2);
var _1d=new GMarker(_18,_1a);
GEvent.addListener(_1d,"click",function(){
map.showMapBlowup(_1d.getPoint());
});
return _1d;
}
function createTabMarker(_1e,_1f,_20){
var _21=new GIcon();
_21.image=_20[0];
_21.iconSize=new GSize(_20[1],_20[2]);
subt=_20[2]/100*11;
subt=Math.ceil(subt);
var _22=_20[2]-subt;
_21.iconAnchor=new GPoint(10,_22);
var _23=_20[2]/3;
_21.infoWindowAnchor=new GPoint(_23,2);
var _24=new GMarker(_1e,_21);
GEvent.addListener(_24,"click",function(){
_24.openInfoWindowTabsHtml(_1f);
});
return _24;
}
function createLinkMarker(_25,_26,_27){
var _28=new GIcon();
_28.image=_27[0];
_28.iconSize=new GSize(_27[1],_27[2]);
subt=_27[2]/100*11;
subt=Math.ceil(subt);
var _29=_27[2]-subt;
_28.iconAnchor=new GPoint(10,_29);
var _2a=_27[2]/3;
_28.infoWindowAnchor=new GPoint(_2a,2);
var _2b=new GMarker(_25,_28);
GEvent.addListener(_2b,"click",function(){
window.location.href=_26;
});
return _2b;
}
function createOtherMarker(_2c,_2d,_2e){
var _2f=new GIcon();
_2f.image=_2e[0];
_2f.iconSize=new GSize(_2e[1],_2e[2]);
subt=_2e[2]/100*11;
subt=Math.ceil(subt);
var _30=_2e[2]-subt;
_2f.iconAnchor=new GPoint(10,_30);
var _31=_2e[2]/3;
_31=Math.floor(_31);
_2f.infoWindowAnchor=new GPoint(_31,2);
var _32=new GMarker(_2c,_2f);
var _33=_2d;
GEvent.addListener(_32,"click",function(){
_32.openInfoWindowHtml(_33);
});
return _32;
}
var arrowIcon=new GIcon();
arrowIcon.iconSize=new GSize(24,24);
arrowIcon.shadowSize=new GSize(1,1);
arrowIcon.iconAnchor=new GPoint(12,12);
arrowIcon.infoWindowAnchor=new GPoint(0,0);
var degreesPerRadian=180/Math.PI;
function bearing(_34,to){
var _36=_34.latRadians();
var _37=_34.lngRadians();
var _38=to.latRadians();
var _39=to.lngRadians();
var _3a=-Math.atan2(Math.sin(_37-_39)*Math.cos(_38),Math.cos(_36)*Math.sin(_38)-Math.sin(_36)*Math.cos(_38)*Math.cos(_37-_39));
if(_3a<0){
_3a+=Math.PI*2;
}
_3a=_3a*degreesPerRadian;
_3a=_3a.toFixed(1);
return _3a;
}
function arrowHead(_3b){
var p1=_3b[_3b.length-1];
var p2=_3b[_3b.length-2];
var dir=bearing(p2,p1);
var dir=Math.round(dir/3)*3;
while(dir>=120){
dir-=120;
}
arrowIcon.image="/img/dir_"+dir+".png";
map.addOverlay(new GMarker(p1,arrowIcon));
}
function midArrows(_3f){
for(var i=1;i<_3f.length-1;i++){
var p1=_3f[i-1];
var p2=_3f[i+1];
var dir=bearing(p1,p2);
var dir=Math.round(dir/3)*3;
while(dir>=120){
dir-=120;
}
arrowIcon.image="/img/dir_"+dir+".png";
map.addOverlay(new GMarker(_3f[i],arrowIcon));
}
}
function geocodeAddress(_44,typ,_46,_47){
geocoder.getLatLng(_44,function(_48){
if(!_48){
alert(_44+" nicht gefunden");
}else{
var lat=_48.lat();
var lng=_48.lng();
map.setCenter(_48,_47);
if(typ=="otherMarker"){
_46=createOtherMarker(_48,_44,_46);
}else{
if(typ=="marker"){
_46=createMarker(_48,_46);
}else{
if(typ=="mapMarker"){
_46=createMapMarker(_48,_46);
}
}
}
map.addOverlay(_46);
}
});
}
function getAdress(){
var _4b=document.getElementById("adresse").value;
return _4b;
}
function tilechk(){
paragraphs=map.getContainer().getElementsByTagName("p").length;
if(paragraphs>4){
map.zoomOut(false,true);
}
}
function wheelevent(e){
if(!e){
e=window.event;
}
if(e.preventDefault){
e.preventDefault();
}
e.returnValue=false;
}

