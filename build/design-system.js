( function ( wp ) {
	'use strict';
	if ( ! wp || ! wp.plugins || ! wp.editor || ! wp.element || ! wp.components || ! wp.apiFetch || ! wp.data ) return;
	var el = wp.element.createElement, Fragment = wp.element.Fragment, useEffect = wp.element.useEffect, useMemo = wp.element.useMemo, useState = wp.element.useState;
	var __ = wp.i18n.__, apiFetch = wp.apiFetch, registerPlugin = wp.plugins.registerPlugin;
	var PluginSidebar = wp.editor.PluginSidebar, PluginSidebarMoreMenuItem = wp.editor.PluginSidebarMoreMenuItem;
	var Button = wp.components.Button, Notice = wp.components.Notice, PanelBody = wp.components.PanelBody, Spinner = wp.components.Spinner, TabPanel = wp.components.TabPanel, TextControl = wp.components.TextControl, TextareaControl = wp.components.TextareaControl, ToggleControl = wp.components.ToggleControl;
	var useSelect = wp.data.useSelect;
	var bootstrap = window.crescoCanvasEditorSettings || { canManageSettings: false, restPath: '/cresco-canvas/v1/', version: 'unknown' };
	var labels = { fontXs:'Text XS',fontSm:'Text small',fontBase:'Body text',fontLg:'Text large',fontXl:'Text XL',h1:'Heading H1',h2:'Heading H2',h3:'Heading H3',h4:'Heading H4',h5:'Heading H5',h6:'Heading H6',space2xs:'Space 2XS',spaceXs:'Space XS',spaceSm:'Space small',spaceMd:'Space medium',spaceLg:'Space large',spaceXl:'Space XL',space2xl:'Space 2XL',space3xl:'Space 3XL',sectionBlock:'Section vertical padding',containerGutter:'Container gutter',gridGap:'Grid gap',radiusSm:'Radius small',radiusMd:'Radius medium',radiusLg:'Radius large',controlHeight:'Control height',buttonPadding:'Button horizontal padding' };
	var breakpointDefinitions = {
		mobile: { label: 'Mobile start', min: 0, max: 767, range: '0-767px' },
		tablet: { label: 'Tablet start', min: 1, max: 1024, range: '768-1024px' },
		laptop: { label: 'Laptop start', min: 2, max: 1439, range: '1025-1439px' },
		desktop: { label: 'Desktop start', min: 3, max: 1919, range: '1440-1919px' },
		wide: { label: 'Widescreen start', min: 4, max: 3840, range: 'from 1920px' }
	};
	function clone( value ){ return JSON.parse( JSON.stringify( value || {} ) ); }
	function cleanValue( value ) {
		if ( value === null || typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean' ) return value;
		if ( Array.isArray( value ) ) return value.map( cleanValue );
		if ( ! value || typeof value !== 'object' ) return undefined;
		var out = {};
		Object.keys( value ).sort().forEach( function ( key ) {
			if ( [ 'clientId', 'originalContent', 'validationIssues', '__unstableBlockSource' ].indexOf( key ) !== -1 ) return;
			var next = cleanValue( value[ key ] );
			if ( typeof next !== 'undefined' ) out[ key ] = next;
		} );
		return out;
	}
	function cleanBlock( block ) {
		return {
			name: String( block && block.name || '' ),
			attributes: cleanValue( block && block.attributes || {} ),
			innerBlocks: ( block && Array.isArray( block.innerBlocks ) ? block.innerBlocks : [] ).map( cleanBlock )
		};
	}
	function safeFilename( value ) {
		return String( value || 'page' ).toLowerCase().replace( /[^a-z0-9]+/g, '-' ).replace( /^-+|-+$/g, '' ).slice( 0, 80 ) || 'page';
	}
	function copyText( text ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) return navigator.clipboard.writeText( text );
		return new Promise( function ( resolve, reject ) {
			var area = document.createElement( 'textarea' ); area.value = text; area.setAttribute( 'readonly', 'readonly' ); area.style.position = 'fixed'; area.style.opacity = '0'; document.body.appendChild( area ); area.select();
			try { document.execCommand( 'copy' ) ? resolve() : reject( new Error( 'copy_failed' ) ); } catch ( error ) { reject( error ); }
			document.body.removeChild( area );
		} );
	}
	function downloadText( text, filename ) {
		var blob = new Blob( [ text ], { type: 'application/json;charset=utf-8' } );
		var url = URL.createObjectURL( blob );
		var link = document.createElement( 'a' ); link.href = url; link.download = filename; document.body.appendChild( link ); link.click(); document.body.removeChild( link ); URL.revokeObjectURL( url );
	}
	function GlobalDesign(){
		var documentState = useSelect( function ( select ) {
			var editor = select( 'core/editor' );
			var blockEditor = select( 'core/block-editor' );
			return {
				id: editor && editor.getCurrentPostId ? editor.getCurrentPostId() : 0,
				type: editor && editor.getCurrentPostType ? editor.getCurrentPostType() : '',
				title: editor && editor.getEditedPostAttribute ? editor.getEditedPostAttribute( 'title' ) : '',
				slug: editor && editor.getEditedPostAttribute ? editor.getEditedPostAttribute( 'slug' ) : '',
				status: editor && editor.getEditedPostAttribute ? editor.getEditedPostAttribute( 'status' ) : '',
				template: editor && editor.getEditedPostAttribute ? editor.getEditedPostAttribute( 'template' ) : '',
				meta: editor && editor.getEditedPostAttribute ? cleanValue( editor.getEditedPostAttribute( 'meta' ) || {} ) : {},
				blocks: blockEditor && blockEditor.getBlocks ? blockEditor.getBlocks().map( cleanBlock ) : []
			};
		}, [] );
		var s=useState(null),settings=s[0],setSettings=s[1],l=useState(true),loading=l[0],setLoading=l[1],sv=useState(false),saving=sv[0],setSaving=sv[1],n=useState(null),notice=n[0],setNotice=n[1],im=useState(''),importValue=im[0],setImportValue=im[1];
		useEffect(function(){ if(!bootstrap.canManageSettings){setLoading(false);return;} apiFetch({path:bootstrap.restPath+'settings'}).then(setSettings).catch(function(error){setNotice({status:'error',message:error&&error.message?error.message:__('Global settings could not be loaded.','cresco-canvas')});}).finally(function(){setLoading(false);});},[]);
		function patch(key,value){var next=clone(settings);next[key]=value;setSettings(next);} function patchMap(group,key,value){var next=clone(settings);next[group]=next[group]||{};next[group][key]=value;setSettings(next);}
		function save(){if(!settings||saving)return;setSaving(true);apiFetch({path:bootstrap.restPath+'settings',method:'POST',data:settings}).then(function(result){setSettings(result);setNotice({status:'success',message:__('Global design saved. Reload the editor to refresh all previews.','cresco-canvas')});}).catch(function(error){setNotice({status:'error',message:error.message||__('Could not save.','cresco-canvas')});}).finally(function(){setSaving(false);});}
		function reset(){if(saving)return;setSaving(true);apiFetch({path:bootstrap.restPath+'settings/reset',method:'POST'}).then(function(result){setSettings(result);setNotice({status:'success',message:__('Global defaults restored.','cresco-canvas')});}).finally(function(){setSaving(false);});}
		function applyImport(){try{var value=JSON.parse(importValue);var globalValue=value&&value.globalDesign?value.globalDesign:value;setSettings(globalValue);setImportValue('');setNotice({status:'success',message:__('Imported global values are ready. Save to apply them.','cresco-canvas')});}catch(error){setNotice({status:'error',message:__('Invalid JSON.','cresco-canvas')});}}
		function ColorField(key,label){return el('label',{className:'cc-global-color'},el('span',null,label),el('input',{type:'color',value:settings[key],onChange:function(event){patch(key,event.target.value);}}),el(TextControl,{hideLabelFromVision:true,label:label,value:settings[key],onChange:function(value){patch(key,value);}}));}
		function TokenFields(keys){return el('div',{className:'cc-global-token-list'},keys.map(function(key){return el(TextControl,{key:key,label:labels[key]||key,help:settings.fluidTokens[key]&&settings.fluidTokens[key].indexOf('clamp(')===0?__('Fluid across devices with clamp().','cresco-canvas'):'',value:settings.fluidTokens[key]||'',onChange:function(value){patchMap('fluidTokens',key,value);}});}));}
		function BreakpointFields(){return el('div',{className:'cc-global-token-list'},Object.keys(settings.breakpoints||{}).map(function(key){var definition=breakpointDefinitions[key]||{label:key.charAt(0).toUpperCase()+key.slice(1),min:0,max:3840,range:''};return el(TextControl,{key:key,type:'number',min:definition.min,max:definition.max,label:definition.label+' (px)',help:definition.range,value:settings.breakpoints[key],onChange:function(value){patchMap('breakpoints',key,Number(value));}});}));}
		var blueprint = useMemo( function () {
			if ( ! settings ) return '';
			return JSON.stringify( {
				$schema: 'https://cresco.example/schemas/canvas-blueprint-v1.json',
				kind: 'cresco-canvas-blueprint',
				schemaVersion: 1,
				pluginVersion: bootstrap.version || 'unknown',
				exportedAt: new Date().toISOString(),
				purpose: 'Portable page configuration for review, generation and reconstruction by ChatGPT or Cresco Canvas.',
				document: cleanValue( documentState ),
				globalDesign: cleanValue( settings )
			}, null, 2 );
		}, [ settings, JSON.stringify( documentState ) ] );
		function copyBlueprint(){copyText(blueprint).then(function(){setNotice({status:'success',message:__('Complete page blueprint copied to the clipboard.','cresco-canvas')});}).catch(function(){setNotice({status:'error',message:__('The browser blocked clipboard access. Select and copy the JSON manually.','cresco-canvas')});});}
		function downloadBlueprint(){downloadText(blueprint,'cresco-blueprint-'+safeFilename(documentState.slug||documentState.title)+'.json');setNotice({status:'success',message:__('Complete page blueprint downloaded.','cresco-canvas')});}
		var content;
		if(!bootstrap.canManageSettings)content=el(Notice,{status:'warning',isDismissible:false},__('You do not have permission to edit global design.','cresco-canvas'));
		else if(loading)content=el('div',{className:'cc-ds-loading'},el(Spinner));
		else if(!settings)content=el(Notice,{status:'error',isDismissible:false},__('Global settings are unavailable.','cresco-canvas'));
		else content=el(Fragment,null,
			notice&&el(Notice,{status:notice.status,isDismissible:true,onRemove:function(){setNotice(null);}},notice.message),
			el('div',{className:'cc-global-intro'},el('strong',null,__('Global responsive design','cresco-canvas')),el('p',null,__('Editable defaults for every Cresco page. Use clamp() for fluid values and breakpoints only for structural changes.','cresco-canvas'))),
			el(TabPanel,{className:'cc-global-tabs',activeClass:'is-active',tabs:[{name:'colors',title:__('Colors','cresco-canvas')},{name:'type',title:__('Type','cresco-canvas')},{name:'spacing',title:__('Spacing','cresco-canvas')},{name:'layout',title:__('Layout','cresco-canvas')},{name:'controls',title:__('Controls','cresco-canvas')},{name:'breakpoints',title:__('Devices','cresco-canvas')}]},function(tab){
				if(tab.name==='colors')return el(PanelBody,{title:__('Global colors','cresco-canvas'),initialOpen:true},ColorField('primary',__('Primary','cresco-canvas')),ColorField('text',__('Text','cresco-canvas')),ColorField('muted',__('Muted','cresco-canvas')),ColorField('background',__('Background','cresco-canvas')));
				if(tab.name==='type')return el(Fragment,null,el(PanelBody,{title:__('Font foundation','cresco-canvas'),initialOpen:true},el(TextControl,{label:__('Font family stack','cresco-canvas'),value:settings.fontFamily,onChange:function(value){patch('fontFamily',value);}})),el(PanelBody,{title:__('Fluid typography','cresco-canvas'),initialOpen:true},TokenFields(['fontXs','fontSm','fontBase','fontLg','fontXl','h1','h2','h3','h4','h5','h6'])));
				if(tab.name==='spacing')return el(PanelBody,{title:__('Fluid spacing scale','cresco-canvas'),initialOpen:true},TokenFields(['space2xs','spaceXs','spaceSm','spaceMd','spaceLg','spaceXl','space2xl','space3xl','sectionBlock','gridGap']));
				if(tab.name==='layout')return el(Fragment,null,el(PanelBody,{title:__('Container widths','cresco-canvas'),initialOpen:true},el(TextControl,{type:'number',label:__('Container max (px)','cresco-canvas'),value:settings.containerMax,onChange:function(value){patch('containerMax',Number(value));}}),el(TextControl,{type:'number',label:__('Content max (px)','cresco-canvas'),value:settings.contentMax,onChange:function(value){patch('contentMax',Number(value));}}),TokenFields(['containerGutter'])),el(PanelBody,{title:__('Responsive radii','cresco-canvas'),initialOpen:true},el(TextControl,{type:'number',label:__('Legacy base radius (px)','cresco-canvas'),value:settings.radius,onChange:function(value){patch('radius',Number(value));}}),TokenFields(['radiusSm','radiusMd','radiusLg'])));
				if(tab.name==='controls')return el(PanelBody,{title:__('Buttons and fields','cresco-canvas'),initialOpen:true},TokenFields(['controlHeight','buttonPadding']));
				return el(PanelBody,{title:__('Structural breakpoints','cresco-canvas'),initialOpen:true},el(Notice,{status:'info',isDismissible:false},__('Ranges: Mobile 0-767px, Tablet 768-1024px, Laptop 1025-1439px, Desktop 1440-1919px, Widescreen from 1920px. Typography and spacing remain fluid.','cresco-canvas')),BreakpointFields());
			}),
			el(PanelBody,{title:__('Blueprint JSON for ChatGPT','cresco-canvas'),initialOpen:true},
				el(Notice,{status:'info',isDismissible:false},__('This JSON contains the current page structure, every block attribute, native Gutenberg settings, Cresco settings and Global Design. It excludes temporary editor IDs.','cresco-canvas')),
				el(TextareaControl,{label:__('Complete page blueprint','cresco-canvas'),readOnly:true,rows:14,value:blueprint}),
				el('div',{className:'cc-ds-actions'},el(Button,{variant:'primary',disabled:!blueprint,onClick:copyBlueprint},__('Copy complete JSON','cresco-canvas')),el(Button,{variant:'secondary',disabled:!blueprint,onClick:downloadBlueprint},__('Download JSON','cresco-canvas')))
			),
			el(PanelBody,{title:__('Global import and maintenance','cresco-canvas'),initialOpen:false},el(TextareaControl,{label:__('Global Design JSON only','cresco-canvas'),readOnly:true,rows:8,value:JSON.stringify(settings,null,2)}),el(TextareaControl,{label:__('Import Global Design or a complete blueprint','cresco-canvas'),rows:8,value:importValue,onChange:setImportValue}),el(Button,{variant:'secondary',disabled:!importValue.trim(),onClick:applyImport},__('Apply global values','cresco-canvas')),el(ToggleControl,{label:__('Remove data on uninstall','cresco-canvas'),checked:!!settings.removeDataOnUninstall,onChange:function(value){patch('removeDataOnUninstall',value);}})),
			el('div',{className:'cc-ds-actions'},el(Button,{variant:'primary',isBusy:saving,disabled:saving,onClick:save},__('Save global design','cresco-canvas')),el(Button,{variant:'tertiary',disabled:saving,onClick:reset},__('Reset defaults','cresco-canvas')))
		);
		return el(Fragment,null,el(PluginSidebarMoreMenuItem,{target:'cresco-canvas-design-system'},__('Global Design','cresco-canvas')),el(PluginSidebar,{className:'cresco-canvas-design-system',icon:'admin-appearance',name:'cresco-canvas-design-system',title:__('Global Design','cresco-canvas')},content));
	}
	registerPlugin('cresco-canvas-design-system',{icon:'admin-appearance',render:GlobalDesign});
} )( window.wp );
