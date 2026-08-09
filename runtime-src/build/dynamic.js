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
var ToggleControl=wp.components.ToggleControl;
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

registerBlockType('cresco/dynamic-image',{
 apiVersion:3,title:__('Dynamic Image','cresco-canvas'),icon:'format-image',category:'cresco-canvas',
 description:__('Render a featured image, post-meta image, or ACF image safely.','cresco-canvas'),
 attributes:{source:{type:'string',default:'featured'},key:{type:'string',default:''},size:{type:'string',default:'large'},altFallback:{type:'string',default:''},linkTo:{type:'string',default:'none'},fallbackUrl:{type:'string',default:''}},
 supports:{html:false,align:['left','center','right','wide','full'],spacing:true},
 edit:function(props){
  var a=props.attributes,set=props.setAttributes,needsKey=a.source!=='featured';
  return el(Fragment,null,
   el(InspectorControls,null,el(PanelBody,{title:__('Dynamic image','cresco-canvas'),initialOpen:true},
    el(SelectControl,{label:__('Source','cresco-canvas'),value:a.source,options:[{label:__('Featured image','cresco-canvas'),value:'featured'},{label:__('Custom field / post meta','cresco-canvas'),value:'meta'},{label:__('ACF image field','cresco-canvas'),value:'acf'}],onChange:function(v){set({source:v});}}),
    needsKey&&el(TextControl,{label:__('Image field key','cresco-canvas'),value:a.key,onChange:function(v){set({key:v});}}),
    el(SelectControl,{label:__('Image size','cresco-canvas'),value:a.size,options:['thumbnail','medium','medium_large','large','full'].map(function(v){return{label:v,value:v};}),onChange:function(v){set({size:v});}}),
    el(TextControl,{label:__('Fallback image URL','cresco-canvas'),value:a.fallbackUrl,onChange:function(v){set({fallbackUrl:v});}}),
    el(TextControl,{label:__('Fallback alt text','cresco-canvas'),value:a.altFallback,onChange:function(v){set({altFallback:v});}}),
    el(SelectControl,{label:__('Link','cresco-canvas'),value:a.linkTo,options:[{label:__('None','cresco-canvas'),value:'none'},{label:__('Current post','cresco-canvas'),value:'post'}],onChange:function(v){set({linkTo:v});}})
   )),
   el('figure',useBlockProps({className:'cresco-dynamic-image is-editor-preview'}),
    el('div',{className:'cresco-dynamic-image__placeholder'},el('span',{className:'dashicons dashicons-format-image'}),el('strong',null,__('Dynamic Image','cresco-canvas')),el('small',null,needsKey?(a.source+': '+(a.key||__('Choose an image field key','cresco-canvas'))):__('Featured image','cresco-canvas')))
   )
  );
 },save:function(){return null;}
});

registerBlockType('cresco/loop',{
 apiVersion:3,title:__('Cresco Loop','cresco-canvas'),icon:'screenoptions',category:'cresco-canvas',
 description:__('Repeat inner blocks for a bounded WordPress query.','cresco-canvas'),
 attributes:{postType:{type:'string',default:'post'},postsPerPage:{type:'number',default:6},order:{type:'string',default:'DESC'},orderby:{type:'string',default:'date'},preset:{type:'string',default:'custom'},taxonomy:{type:'string',default:''},term:{type:'string',default:''},offset:{type:'number',default:0},columns:{type:'number',default:3},pagination:{type:'boolean',default:false},pageParam:{type:'string',default:'cc_page'},emptyMessage:{type:'string',default:''}},
 supports:{html:false,align:['wide','full'],spacing:true},
 edit:function(props){
  var a=props.attributes,set=props.setAttributes;
  return el(Fragment,null,
   el(InspectorControls,null,el(PanelBody,{title:__('Query Builder','cresco-canvas'),initialOpen:true},
    el(SelectControl,{label:__('Query preset','cresco-canvas'),value:a.preset,options:[{label:__('Custom','cresco-canvas'),value:'custom'},{label:__('Recent first','cresco-canvas'),value:'recent'},{label:__('Oldest first','cresco-canvas'),value:'oldest'},{label:__('Alphabetical','cresco-canvas'),value:'alphabetical'},{label:__('Random','cresco-canvas'),value:'random'}],onChange:function(v){set({preset:v});}}),
    el(TextControl,{label:__('Post type slug','cresco-canvas'),value:a.postType,onChange:function(v){set({postType:v});}}),
    el(RangeControl,{label:__('Items per page','cresco-canvas'),value:a.postsPerPage,min:1,max:24,onChange:function(v){set({postsPerPage:v});}}),
    a.preset==='custom'&&el(SelectControl,{label:__('Order by','cresco-canvas'),value:a.orderby,options:['date','modified','title','menu_order','rand'].map(function(v){return{label:v,value:v};}),onChange:function(v){set({orderby:v});}}),
    a.preset==='custom'&&el(SelectControl,{label:__('Order','cresco-canvas'),value:a.order,options:[{label:'DESC',value:'DESC'},{label:'ASC',value:'ASC'}],onChange:function(v){set({order:v});}}),
    el(RangeControl,{label:__('Base offset','cresco-canvas'),value:a.offset,min:0,max:200,onChange:function(v){set({offset:v});}}),
    el(RangeControl,{label:__('Columns','cresco-canvas'),value:a.columns,min:1,max:6,onChange:function(v){set({columns:v});}}),
    el(TextControl,{label:__('Taxonomy slug','cresco-canvas'),value:a.taxonomy,onChange:function(v){set({taxonomy:v});}}),
    el(TextControl,{label:__('Term slug','cresco-canvas'),value:a.term,onChange:function(v){set({term:v});}}),
    el(ToggleControl,{label:__('Enable pagination','cresco-canvas'),checked:!!a.pagination,onChange:function(v){set({pagination:v});}}),
    a.pagination&&el(TextControl,{label:__('Pagination query parameter','cresco-canvas'),help:__('Use a unique value when a page contains more than one loop.','cresco-canvas'),value:a.pageParam,onChange:function(v){set({pageParam:v});}}),
    el(TextControl,{label:__('Empty message','cresco-canvas'),value:a.emptyMessage,onChange:function(v){set({emptyMessage:v});}})
   )),
   el('div',useBlockProps({className:'cresco-loop is-editor-preview',style:{'--cresco-loop-columns':a.columns}}),
    el(Notice,{status:'info',isDismissible:false},__('Inner blocks are rendered once per matching post. Nested Cresco Loops are blocked.','cresco-canvas')),
    el(InnerBlocks,{allowedBlocks:['core/group','core/columns','core/column','core/heading','core/paragraph','core/buttons','core/button','core/list','core/image','core/separator','core/spacer','cresco/container','cresco/dynamic-field','cresco/dynamic-image'],template:[['core/group',{},[['cresco/dynamic-image',{source:'featured',size:'large',linkTo:'post'}],['cresco/dynamic-field',{source:'post',field:'title',tagName:'h3',linkTo:'post'}],['cresco/dynamic-field',{source:'post',field:'excerpt',tagName:'p'}]]]],templateLock:false})
   )
  );
 },save:function(){return el(InnerBlocks.Content);}
});
})(window.wp);
