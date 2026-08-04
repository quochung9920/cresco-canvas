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

registerBlockType('cresco/dynamic-gallery',{
 apiVersion:3,title:__('Dynamic Gallery','cresco-canvas'),icon:'format-gallery',category:'cresco-canvas',
 description:__('Render an ACF Gallery or image-list custom field.','cresco-canvas'),
 attributes:{source:{type:'string',default:'acf'},key:{type:'string',default:''},columns:{type:'number',default:3},size:{type:'string',default:'large'},limit:{type:'number',default:12}},
 supports:{html:false,align:['wide','full'],spacing:true},
 edit:function(props){var a=props.attributes,set=props.setAttributes;
  return el(Fragment,null,
   el(InspectorControls,null,el(PanelBody,{title:__('Gallery source','cresco-canvas'),initialOpen:true},
    el(SelectControl,{label:__('Source','cresco-canvas'),value:a.source,options:[{label:__('ACF Gallery','cresco-canvas'),value:'acf'},{label:__('Post meta image list','cresco-canvas'),value:'meta'}],onChange:function(v){set({source:v});}}),
    el(TextControl,{label:__('Field key','cresco-canvas'),value:a.key,onChange:function(v){set({key:v});}}),
    el(RangeControl,{label:__('Columns','cresco-canvas'),value:a.columns,min:1,max:6,onChange:function(v){set({columns:v});}}),
    el(RangeControl,{label:__('Image limit','cresco-canvas'),value:a.limit,min:1,max:24,onChange:function(v){set({limit:v});}}),
    el(SelectControl,{label:__('Image size','cresco-canvas'),value:a.size,options:['thumbnail','medium','medium_large','large','full'].map(function(v){return{label:v,value:v};}),onChange:function(v){set({size:v});}})
   )),
   el('div',useBlockProps({className:'cresco-dynamic-gallery is-editor-preview',style:{'--cresco-gallery-columns':a.columns}}),
    el(Notice,{status:'info',isDismissible:false},__('Gallery images are resolved from the current post on the server.','cresco-canvas')),
    el('strong',null,(a.source||'acf')+': '+(a.key||__('Choose a field key','cresco-canvas')))
   )
  );
 },save:function(){return null;}
});

registerBlockType('cresco/relationship-loop',{
 apiVersion:3,title:__('Relationship Loop','cresco-canvas'),icon:'admin-links',category:'cresco-canvas',
 description:__('Repeat native blocks for posts selected by an ACF Relationship or Post Object field.','cresco-canvas'),
 attributes:{source:{type:'string',default:'acf'},key:{type:'string',default:''},limit:{type:'number',default:12},columns:{type:'number',default:3},emptyMessage:{type:'string',default:''}},
 supports:{html:false,align:['wide','full'],spacing:true},
 edit:function(props){var a=props.attributes,set=props.setAttributes;
  return el(Fragment,null,
   el(InspectorControls,null,el(PanelBody,{title:__('Relationship source','cresco-canvas'),initialOpen:true},
    el(SelectControl,{label:__('Source','cresco-canvas'),value:a.source,options:[{label:__('ACF Relationship / Post Object','cresco-canvas'),value:'acf'},{label:__('Post meta IDs','cresco-canvas'),value:'meta'}],onChange:function(v){set({source:v});}}),
    el(TextControl,{label:__('Field key','cresco-canvas'),value:a.key,onChange:function(v){set({key:v});}}),
    el(RangeControl,{label:__('Item limit','cresco-canvas'),value:a.limit,min:1,max:24,onChange:function(v){set({limit:v});}}),
    el(RangeControl,{label:__('Columns','cresco-canvas'),value:a.columns,min:1,max:6,onChange:function(v){set({columns:v});}}),
    el(TextControl,{label:__('Empty message','cresco-canvas'),value:a.emptyMessage,onChange:function(v){set({emptyMessage:v});}})
   )),
   el('div',useBlockProps({className:'cresco-relationship-loop is-editor-preview',style:{'--cresco-relationship-columns':a.columns}}),
    el(Notice,{status:'info',isDismissible:false},__('Inner blocks are rendered once for each related published post.','cresco-canvas')),
    el(InnerBlocks,{allowedBlocks:['core/group','core/heading','core/paragraph','core/buttons','core/button','core/image','cresco/dynamic-field','cresco/dynamic-image'],template:[['core/group',{},[['cresco/dynamic-image',{source:'featured',size:'medium',linkTo:'post'}],['cresco/dynamic-field',{source:'post',field:'title',tagName:'h3',linkTo:'post'}],['cresco/dynamic-field',{source:'post',field:'excerpt',tagName:'p'}]]]],templateLock:false})
   )
  );
 },save:function(){return el(InnerBlocks.Content);}
});
})(window.wp);
