(function(wp){
'use strict';
var el=wp.element.createElement;
var Fragment=wp.element.Fragment;
var components=wp.components;
var data=wp.data;
var editor=wp.editor;
var plugins=wp.plugins;
var i18n=wp.i18n;
var TYPES=[['header','Header'],['footer','Footer'],['single','Single'],['page','Page'],['archive','Archive'],['search','Search'],['404','404']];
var RULES=[['entire_site','Entire site'],['front_page','Front page'],['blog_home','Blog home'],['singular','Any singular'],['post_type','Post type'],['post_id','Specific post ID'],['archive','Any archive'],['taxonomy','Taxonomy archive'],['search','Search results'],['404','404 page'],['logged_in','Logged-in users'],['logged_out','Logged-out visitors']];
function Panel(){
 var meta=data.useSelect(function(select){return select('core/editor').getEditedPostAttribute('meta')||{};},[]);
 var editPost=data.useDispatch('core/editor').editPost;
 var conditions=Array.isArray(meta._cresco_template_conditions)?meta._cresco_template_conditions:[];
 function setMeta(patch){editPost({meta:Object.assign({},meta,patch)});}
 function updateCondition(index,patch){var next=conditions.slice();next[index]=Object.assign({},next[index],patch);setMeta({_cresco_template_conditions:next});}
 function removeCondition(index){setMeta({_cresco_template_conditions:conditions.filter(function(_,i){return i!==index;})});}
 return el(editor.PluginDocumentSettingPanel,{name:'cresco-theme-builder',title:i18n.__('Cresco Theme Builder','cresco-canvas'),className:'cresco-theme-builder-panel'},
  el(components.SelectControl,{label:i18n.__('Template type','cresco-canvas'),value:meta._cresco_template_type||'',options:[{label:i18n.__('Select a type','cresco-canvas'),value:''}].concat(TYPES.map(function(item){return {label:item[1],value:item[0]};})),onChange:function(value){setMeta({_cresco_template_type:value});}}),
  el(components.TextControl,{label:i18n.__('Priority','cresco-canvas'),type:'number',min:0,max:1000,value:meta._cresco_template_priority||10,onChange:function(value){setMeta({_cresco_template_priority:Math.max(0,Math.min(1000,Number(value)||0))});},help:i18n.__('Higher priority wins when multiple templates match.','cresco-canvas')}),
  el('div',{className:'cc-theme-conditions'},
   el('strong',null,i18n.__('Display conditions','cresco-canvas')),
   conditions.length===0&&el('p',{className:'cc-theme-help'},i18n.__('No conditions means the template applies everywhere for its type.','cresco-canvas')),
   conditions.map(function(condition,index){return el('div',{className:'cc-theme-condition',key:index},
    el(components.SelectControl,{label:i18n.__('Operator','cresco-canvas'),value:condition.operator||'include',options:[{label:i18n.__('Include','cresco-canvas'),value:'include'},{label:i18n.__('Exclude','cresco-canvas'),value:'exclude'}],onChange:function(value){updateCondition(index,{operator:value});}}),
    el(components.SelectControl,{label:i18n.__('Rule','cresco-canvas'),value:condition.rule||'entire_site',options:RULES.map(function(item){return {label:item[1],value:item[0]};}),onChange:function(value){updateCondition(index,{rule:value});}}),
    el(components.TextControl,{label:i18n.__('Value','cresco-canvas'),value:condition.value||'',onChange:function(value){updateCondition(index,{value:value});},help:i18n.__('Used by Post type, Post ID, and Taxonomy rules.','cresco-canvas')}),
    el(components.Button,{isDestructive:true,variant:'tertiary',onClick:function(){removeCondition(index);}},i18n.__('Remove condition','cresco-canvas'))
   );}),
   el(components.Button,{variant:'secondary',disabled:conditions.length>=24,onClick:function(){setMeta({_cresco_template_conditions:conditions.concat([{operator:'include',rule:'entire_site',value:''}])});}},i18n.__('Add condition','cresco-canvas'))
  )
 );
}
plugins.registerPlugin('cresco-theme-builder',{render:function(){return el(Fragment,null,el(Panel));}});
})(window.wp);
