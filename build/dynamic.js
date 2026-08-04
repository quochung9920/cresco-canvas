(function(wp){
'use strict';
if(!wp||!wp.blocks||!wp.blockEditor||!wp.components||!wp.element||!wp.i18n){return;}
var el=wp.element.createElement;
var Fragment=wp.element.Fragment;
var __=wp.i18n.__;
var registerBlockType=wp.blocks.registerBlockType;
var InspectorControls=wp.blockEditor.InspectorControls;
var InnerBlocks=wp.blockEditor.InnerBlocks;
var useBlockProps=wp.blockEditor.useBlockProps;
var PanelBody=wp.components.PanelBody;
var SelectControl=wp.components.SelectControl;
var TextControl=wp.components.TextControl;
var RangeControl=wp.components.RangeControl;
var Notice=wp.components.Notice;

registerBlockType('cresco/dynamic-field',{
 apiVersion:3,title:__('Dynamic Field','cresco-canvas'),icon:'database',category:'cresco-canvas',
 description:__('Render post, meta, ACF, or site data on the server.','cresco-canvas'),
 attributes:{source:{type:'string',default:'post'},field:{type:'string',default:'title'},key:{type:'string',default:''},fallback:{type:'string',default:''},tagName:{type:'string',default:'span'},linkTo:{type:'string',default:'none'}},
 supports:{html:false,color:true,typography:true,spacing:true},
 edit:function(props){
  var a=props.attributes,set=props.setAttributes;
  var needsKey=a.source==='meta'||a.source==='acf';
  var fieldOptions=a.source==='site'?[{label:__('Site title','cresco-canvas'),value:'title'},{label:__('Site description','cresco-canvas'),value:'description'}]:[
   {label:__('Title','cresco-canvas'),value:'title'},{label:__('Excerpt','cresco-canvas'),value:'excerpt'},{label:__('Content text','cresco-canvas'),value:'content'},{label:__('Date','cresco-canvas'),value:'date'},{label:__('Modified date','cresco-canvas'),value:'modified'},{label:__('Author','cresco-canvas'),value:'author'},{label:__('Permalink','cresco-canvas'),value:'permalink'},{label:__('Featured image URL','cresco-canvas'),value:'featured_image_url'}
  ];
  return el(Fragment,null,
   el(InspectorControls,null,el(PanelBody,{title:__('Dynamic source','cresco-canvas'),initialOpen:true},
    el(SelectControl,{label:__('Source','cresco-canvas'),value:a.source,options:[{label:__('Post','cresco-canvas'),value:'post'},{label:__('Custom field / post meta','cresco-canvas'),value:'meta'},{label:__('ACF field','cresco-canvas'),value:'acf'},{label:__('Site','cresco-canvas'),value:'site'}],onChange:function(v){set({source:v});}}),
    !needsKey&&el(SelectControl,{label:__('Field','cresco-canvas'),value:a.field,options:fieldOptions,onChange:function(v){set({field:v});}}),
    needsKey&&el(TextControl,{label:__('Field key','cresco-canvas'),value:a.key,onChange:function(v){set({key:v});}}),
    el(TextControl,{label:__('Fallback','cresco-canvas'),value:a.fallback,onChange:function(v){set({fallback:v});}}),
    el(SelectControl,{label:__('HTML tag','cresco-canvas'),value:a.tagName,options:['span','p','div','h1','h2','h3','h4','h5','h6'].map(function(v){return{label:v,value:v};}),onChange:function(v){set({tagName:v});}}),
    el(SelectControl,{label:__('Link','cresco-canvas'),value:a.linkTo,options:[{label:__('None','cresco-canvas'),value:'none'},{label:__('Current post','cresco-canvas'),value:'post'}],onChange:function(v){set({linkTo:v});}})
   )),
   el('div',useBlockProps({className:'cresco-dynamic-field is-editor-preview'}),
    el('strong',null,__('Dynamic Field','cresco-canvas')),
    el('span',null,needsKey?(a.source+': '+(a.key||__('Choose a field key','cresco-canvas'))):(a.source+': '+a.field)),
    a.fallback&&el('small',null,__('Fallback: ','cresco-canvas')+a.fallback)
   )
  );
 },save:function(){return null;}
});

registerBlockType('cresco/loop',{
 apiVersion:3,title:__('Cresco Loop','cresco-canvas'),icon:'screenoptions',category:'cresco-canvas',
 description:__('Repeat inner blocks for a bounded WordPress query.','cresco-canvas'),
 attributes:{postType:{type:'string',default:'post'},postsPerPage:{type:'number',default:6},order:{type:'string',default:'DESC'},orderby:{type:'string',default:'date'},taxonomy:{type:'string',default:''},term:{type:'string',default:''},offset:{type:'number',default:0},columns:{type:'number',default:3},emptyMessage:{type:'string',default:''}},
 supports:{html:false,align:['wide','full'],spacing:true},
 edit:function(props){
  var a=props.attributes,set=props.setAttributes;
  return el(Fragment,null,
   el(InspectorControls,null,el(PanelBody,{title:__('Query Builder','cresco-canvas'),initialOpen:true},
    el(TextControl,{label:__('Post type slug','cresco-canvas'),value:a.postType,onChange:function(v){set({postType:v});}}),
    el(RangeControl,{label:__('Items','cresco-canvas'),value:a.postsPerPage,min:1,max:24,onChange:function(v){set({postsPerPage:v});}}),
    el(SelectControl,{label:__('Order by','cresco-canvas'),value:a.orderby,options:['date','modified','title','menu_order','rand'].map(function(v){return{label:v,value:v};}),onChange:function(v){set({orderby:v});}}),
    el(SelectControl,{label:__('Order','cresco-canvas'),value:a.order,options:[{label:'DESC',value:'DESC'},{label:'ASC',value:'ASC'}],onChange:function(v){set({order:v});}}),
    el(RangeControl,{label:__('Offset','cresco-canvas'),value:a.offset,min:0,max:200,onChange:function(v){set({offset:v});}}),
    el(RangeControl,{label:__('Columns','cresco-canvas'),value:a.columns,min:1,max:6,onChange:function(v){set({columns:v});}}),
    el(TextControl,{label:__('Taxonomy slug','cresco-canvas'),value:a.taxonomy,onChange:function(v){set({taxonomy:v});}}),
    el(TextControl,{label:__('Term slug','cresco-canvas'),value:a.term,onChange:function(v){set({term:v});}}),
    el(TextControl,{label:__('Empty message','cresco-canvas'),value:a.emptyMessage,onChange:function(v){set({emptyMessage:v});}})
   )),
   el('div',useBlockProps({className:'cresco-loop is-editor-preview',style:{'--cresco-loop-columns':a.columns}}),
    el(Notice,{status:'info',isDismissible:false},__('Inner blocks are rendered once per matching post on the frontend.','cresco-canvas')),
    el(InnerBlocks,{template:[['core/group',{},[['cresco/dynamic-field',{source:'post',field:'title',tagName:'h3',linkTo:'post'}],['cresco/dynamic-field',{source:'post',field:'excerpt',tagName:'p'}]]]],templateLock:false})
   )
  );
 },save:function(){return el(InnerBlocks.Content);}
});
})(window.wp);
