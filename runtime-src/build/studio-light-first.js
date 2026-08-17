(function(window,document){
'use strict';
var THEME_KEY='cresco-studio-ux-theme';
var MIGRATION_KEY='cresco-studio-light-first-v1';

function readTheme(){
	try{
		var raw=window.localStorage.getItem(THEME_KEY);
		if(!raw)return null;
		try{return JSON.parse(raw);}catch(e){return raw;}
	}catch(e){return null;}
}
function writeTheme(value){
	try{window.localStorage.setItem(THEME_KEY,JSON.stringify(value));}catch(e){}
}
function migrated(){
	try{return window.localStorage.getItem(MIGRATION_KEY)==='1';}catch(e){return true;}
}
function markMigrated(){
	try{window.localStorage.setItem(MIGRATION_KEY,'1');}catch(e){}
}
function resolvedTheme(value){
	if(value==='dark')return'dark';
	if(value==='system'){
		return window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';
	}
	return'light';
}
function apply(value){
	var resolved=resolvedTheme(value);
	document.documentElement.setAttribute('data-cc-theme',resolved);
	var app=document.querySelector('#cresco-canvas-standalone-editor .cc-studio-app');
	if(app)app.dataset.uxTheme=value||'light';
}

var theme=readTheme();
if(!migrated()){
	if(!theme||theme==='system'){
		theme='light';
		writeTheme(theme);
	}
	markMigrated();
}
if(theme!=='light'&&theme!=='dark'&&theme!=='system'){
	theme='light';
	writeTheme(theme);
}
apply(theme||'light');

if(document.readyState==='loading'){
	document.addEventListener('DOMContentLoaded',function(){apply(readTheme()||'light');},{once:true});
}else{
	window.setTimeout(function(){apply(readTheme()||'light');},0);
}
})(window,document);
