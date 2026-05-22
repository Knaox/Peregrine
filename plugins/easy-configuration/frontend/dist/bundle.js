(function(h,z,Ce,E){"use strict";var W={exports:{}},D={};/**
 * @license React
 * react-jsx-runtime.production.js
 *
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */var oe;function Te(){if(oe)return D;oe=1;var e=Symbol.for("react.transitional.element"),t=Symbol.for("react.fragment");function a(n,s,c){var l=null;if(c!==void 0&&(l=""+c),s.key!==void 0&&(l=""+s.key),"key"in s){c={};for(var i in s)i!=="key"&&(c[i]=s[i])}else c=s;return s=c.ref,{$$typeof:e,type:n,key:l,ref:s!==void 0?s:null,props:c}}return D.Fragment=t,D.jsx=a,D.jsxs=a,D}var J={};/**
 * @license React
 * react-jsx-runtime.development.js
 *
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */var ne;function Se(){return ne||(ne=1,process.env.NODE_ENV!=="production"&&(function(){function e(o){if(o==null)return null;if(typeof o=="function")return o.$$typeof===Fr?null:o.displayName||o.name||null;if(typeof o=="string")return o;switch(o){case S:return"Fragment";case U:return"Profiler";case M:return"StrictMode";case Pr:return"Suspense";case Or:return"SuspenseList";case Ir:return"Activity"}if(typeof o=="object")switch(typeof o.tag=="number"&&console.error("Received an unexpected object in getComponentNameFromType(). This is likely a bug in React. Please file an issue."),o.$$typeof){case p:return"Portal";case Ar:return o.displayName||"Context";case Rr:return(o._context.displayName||"Context")+".Consumer";case zr:var m=o.render;return o=o.displayName,o||(o=m.displayName||m.name||"",o=o!==""?"ForwardRef("+o+")":"ForwardRef"),o;case Mr:return m=o.displayName||null,m!==null?m:e(o.type)||"Memo";case Z:m=o._payload,o=o._init;try{return e(o(m))}catch{}}return null}function t(o){return""+o}function a(o){try{t(o);var m=!1}catch{m=!0}if(m){m=console;var x=m.error,b=typeof Symbol=="function"&&Symbol.toStringTag&&o[Symbol.toStringTag]||o.constructor.name||"Object";return x.call(m,"The provided key is an unsupported type %s. This value must be coerced to a string before using it here.",b),t(o)}}function n(o){if(o===S)return"<>";if(typeof o=="object"&&o!==null&&o.$$typeof===Z)return"<...>";try{var m=e(o);return m?"<"+m+">":"<...>"}catch{return"<...>"}}function s(){var o=ee.A;return o===null?null:o.getOwner()}function c(){return Error("react-stack-top-frame")}function l(o){if(je.call(o,"key")){var m=Object.getOwnPropertyDescriptor(o,"key").get;if(m&&m.isReactWarning)return!1}return o.key!==void 0}function i(o,m){function x(){we||(we=!0,console.error("%s: `key` is not a prop. Trying to access it will result in `undefined` being returned. If you need to access the same value within the child component, you should pass it as a different prop. (https://react.dev/link/special-props)",m))}x.isReactWarning=!0,Object.defineProperty(o,"key",{get:x,configurable:!0})}function u(){var o=e(this.type);return ke[o]||(ke[o]=!0,console.error("Accessing element.ref was removed in React 19. ref is now a regular prop. It will be removed from the JSX Element type in a future release.")),o=this.props.ref,o!==void 0?o:null}function d(o,m,x,b,G,te){var y=x.ref;return o={$$typeof:f,type:o,key:m,props:x,_owner:b},(y!==void 0?y:null)!==null?Object.defineProperty(o,"ref",{enumerable:!1,get:u}):Object.defineProperty(o,"ref",{enumerable:!1,value:null}),o._store={},Object.defineProperty(o._store,"validated",{configurable:!1,enumerable:!1,writable:!0,value:0}),Object.defineProperty(o,"_debugInfo",{configurable:!1,enumerable:!1,writable:!0,value:null}),Object.defineProperty(o,"_debugStack",{configurable:!1,enumerable:!1,writable:!0,value:G}),Object.defineProperty(o,"_debugTask",{configurable:!1,enumerable:!1,writable:!0,value:te}),Object.freeze&&(Object.freeze(o.props),Object.freeze(o)),o}function g(o,m,x,b,G,te){var y=m.children;if(y!==void 0)if(b)if(Lr(y)){for(b=0;b<y.length;b++)_(y[b]);Object.freeze&&Object.freeze(y)}else console.error("React.jsx: Static children should always be an array. You are likely explicitly calling React.jsxs or React.jsxDEV. Use the Babel transform instead.");else _(y);if(je.call(m,"key")){y=e(o);var Y=Object.keys(m).filter(function($r){return $r!=="key"});b=0<Y.length?"{key: someKey, "+Y.join(": ..., ")+": ...}":"{key: someKey}",Ee[y+b]||(Y=0<Y.length?"{"+Y.join(": ..., ")+": ...}":"{}",console.error(`A props object containing a "key" prop is being spread into JSX:
  let props = %s;
  <%s {...props} />
React keys must be passed directly to JSX without using spread:
  let props = %s;
  <%s key={someKey} {...props} />`,b,y,Y,y),Ee[y+b]=!0)}if(y=null,x!==void 0&&(a(x),y=""+x),l(m)&&(a(m.key),y=""+m.key),"key"in m){x={};for(var ae in m)ae!=="key"&&(x[ae]=m[ae])}else x=m;return y&&i(x,typeof o=="function"?o.displayName||o.name||"Unknown":o),d(o,y,x,s(),G,te)}function _(o){j(o)?o._store&&(o._store.validated=1):typeof o=="object"&&o!==null&&o.$$typeof===Z&&(o._payload.status==="fulfilled"?j(o._payload.value)&&o._payload.value._store&&(o._payload.value._store.validated=1):o._store&&(o._store.validated=1))}function j(o){return typeof o=="object"&&o!==null&&o.$$typeof===f}var v=h,f=Symbol.for("react.transitional.element"),p=Symbol.for("react.portal"),S=Symbol.for("react.fragment"),M=Symbol.for("react.strict_mode"),U=Symbol.for("react.profiler"),Rr=Symbol.for("react.consumer"),Ar=Symbol.for("react.context"),zr=Symbol.for("react.forward_ref"),Pr=Symbol.for("react.suspense"),Or=Symbol.for("react.suspense_list"),Mr=Symbol.for("react.memo"),Z=Symbol.for("react.lazy"),Ir=Symbol.for("react.activity"),Fr=Symbol.for("react.client.reference"),ee=v.__CLIENT_INTERNALS_DO_NOT_USE_OR_WARN_USERS_THEY_CANNOT_UPGRADE,je=Object.prototype.hasOwnProperty,Lr=Array.isArray,re=console.createTask?console.createTask:function(){return null};v={react_stack_bottom_frame:function(o){return o()}};var we,ke={},_e=v.react_stack_bottom_frame.bind(v,c)(),Ne=re(n(c)),Ee={};J.Fragment=S,J.jsx=function(o,m,x){var b=1e4>ee.recentlyCreatedOwnerStacks++;return g(o,m,x,!1,b?Error("react-stack-top-frame"):_e,b?re(n(o)):Ne)},J.jsxs=function(o,m,x){var b=1e4>ee.recentlyCreatedOwnerStacks++;return g(o,m,x,!0,b?Error("react-stack-top-frame"):_e,b?re(n(o)):Ne)}})()),J}var se;function Re(){return se||(se=1,process.env.NODE_ENV==="production"?W.exports=Te():W.exports=Se()),W.exports}var r=Re();function ie(e){var t,a,n="";if(typeof e=="string"||typeof e=="number")n+=e;else if(typeof e=="object")if(Array.isArray(e)){var s=e.length;for(t=0;t<s;t++)e[t]&&(a=ie(e[t]))&&(n&&(n+=" "),n+=a)}else for(a in e)e[a]&&(n&&(n+=" "),n+=a);return n}function k(){for(var e,t,a=0,n="",s=arguments.length;a<s;a++)(e=arguments[a])&&(t=ie(e))&&(n&&(n+=" "),n+=t);return n}/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ce=(...e)=>e.filter((t,a,n)=>!!t&&t.trim()!==""&&n.indexOf(t)===a).join(" ").trim();/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Ae=e=>e.replace(/([a-z0-9])([A-Z])/g,"$1-$2").toLowerCase();/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ze=e=>e.replace(/^([A-Z])|[\s-_]+(\w)/g,(t,a,n)=>n?n.toUpperCase():a.toLowerCase());/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const le=e=>{const t=ze(e);return t.charAt(0).toUpperCase()+t.slice(1)};/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */var H={xmlns:"http://www.w3.org/2000/svg",width:24,height:24,viewBox:"0 0 24 24",fill:"none",stroke:"currentColor",strokeWidth:2,strokeLinecap:"round",strokeLinejoin:"round"};/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Pe=e=>{for(const t in e)if(t.startsWith("aria-")||t==="role"||t==="title")return!0;return!1},Oe=h.createContext({}),Me=()=>h.useContext(Oe),Ie=h.forwardRef(({color:e,size:t,strokeWidth:a,absoluteStrokeWidth:n,className:s="",children:c,iconNode:l,...i},u)=>{const{size:d=24,strokeWidth:g=2,absoluteStrokeWidth:_=!1,color:j="currentColor",className:v=""}=Me()??{},f=n??_?Number(a??g)*24/Number(t??d):a??g;return h.createElement("svg",{ref:u,...H,width:t??d??H.width,height:t??d??H.height,stroke:e??j,strokeWidth:f,className:ce("lucide",v,s),...!c&&!Pe(i)&&{"aria-hidden":"true"},...i},[...l.map(([p,S])=>h.createElement(p,S)),...Array.isArray(c)?c:[c]])});/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const w=(e,t)=>{const a=h.forwardRef(({className:n,...s},c)=>h.createElement(Ie,{ref:c,iconNode:t,className:ce(`lucide-${Ae(le(e))}`,`lucide-${e}`,n),...s}));return a.displayName=le(e),a};/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Fe=w("arrow-left",[["path",{d:"m12 19-7-7 7-7",key:"1l729n"}],["path",{d:"M19 12H5",key:"x3x0zl"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const de=w("check",[["path",{d:"M20 6 9 17l-5-5",key:"1gmf2c"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Le=w("circle-check",[["circle",{cx:"12",cy:"12",r:"10",key:"1mglay"}],["path",{d:"m9 12 2 2 4-4",key:"dzmm74"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const $e=w("circle-question-mark",[["circle",{cx:"12",cy:"12",r:"10",key:"1mglay"}],["path",{d:"M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3",key:"1u773s"}],["path",{d:"M12 17h.01",key:"p32p05"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Ye=w("circle-x",[["circle",{cx:"12",cy:"12",r:"10",key:"1mglay"}],["path",{d:"m15 9-6 6",key:"1uzhvr"}],["path",{d:"m9 9 6 6",key:"z0biqf"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const De=w("download",[["path",{d:"M12 15V3",key:"m9g1x1"}],["path",{d:"M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4",key:"ih7n3h"}],["path",{d:"m7 10 5 5 5-5",key:"brsn70"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Je=w("file-plus-corner",[["path",{d:"M11.35 22H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.706.706l3.588 3.588A2.4 2.4 0 0 1 20 8v5.35",key:"17jvcc"}],["path",{d:"M14 2v5a1 1 0 0 0 1 1h5",key:"wfsgrz"}],["path",{d:"M14 19h6",key:"bvotb8"}],["path",{d:"M17 16v6",key:"18yu1i"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const We=w("info",[["circle",{cx:"12",cy:"12",r:"10",key:"1mglay"}],["path",{d:"M12 16v-4",key:"1dtifu"}],["path",{d:"M12 8h.01",key:"e9boi3"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Ve=w("pencil",[["path",{d:"M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z",key:"1a8usu"}],["path",{d:"m15 5 4 4",key:"1mk7zo"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Ue=w("rotate-ccw",[["path",{d:"M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8",key:"1357e3"}],["path",{d:"M3 3v5h5",key:"1xhq8a"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Ge=w("save",[["path",{d:"M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z",key:"1c8476"}],["path",{d:"M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7",key:"1ydtos"}],["path",{d:"M7 3v4a1 1 0 0 0 1 1h7",key:"t51u73"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const He=w("search",[["path",{d:"m21 21-4.34-4.34",key:"14j7rj"}],["circle",{cx:"11",cy:"11",r:"8",key:"4ej97u"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Be=w("trash-2",[["path",{d:"M10 11v6",key:"nco0om"}],["path",{d:"M14 11v6",key:"outv1u"}],["path",{d:"M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6",key:"miytrc"}],["path",{d:"M3 6h18",key:"d0wm0j"}],["path",{d:"M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2",key:"e791ji"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Xe=w("triangle-alert",[["path",{d:"m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3",key:"wmoenq"}],["path",{d:"M12 9v4",key:"juzpu7"}],["path",{d:"M12 17h.01",key:"p32p05"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Ke=w("upload",[["path",{d:"M12 3v12",key:"1x0j5s"}],["path",{d:"m17 8-5-5-5 5",key:"7q97r8"}],["path",{d:"M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4",key:"ih7n3h"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Qe=w("x",[["path",{d:"M18 6 6 18",key:"1bl5f8"}],["path",{d:"m6 6 12 12",key:"d8bk6v"}]]),ue=h.createContext(null);function me(){const e=h.useContext(ue);if(e===null)throw new Error("useToast must be used within a ToastProvider");return e}function qe({children:e}){const[t,a]=h.useState([]),n=h.useRef(0),s=h.useCallback(i=>{a(u=>u.filter(d=>d.id!==i))},[]),c=h.useCallback((i,u="info")=>{n.current+=1;const d=n.current;a(g=>[...g,{id:d,variant:u,message:i}]),window.setTimeout(()=>s(d),4500)},[s]),l=h.useMemo(()=>({show:c,success:i=>c(i,"success"),error:i=>c(i,"error"),warning:i=>c(i,"warning")}),[c]);return r.jsxs(ue.Provider,{value:l,children:[e,t.length>0&&r.jsx("div",{className:"ec-toast-host",children:t.map(i=>r.jsxs("div",{className:k("ec-toast",`ec-toast-${i.variant}`),role:"status",onClick:()=>s(i.id),children:[r.jsx("span",{className:"ec-toast-icon",children:r.jsx(Ze,{variant:i.variant})}),r.jsx("span",{children:i.message})]},i.id))})]})}function Ze({variant:e}){return e==="success"?r.jsx(Le,{size:16}):e==="error"?r.jsx(Ye,{size:16}):e==="warning"?r.jsx(Xe,{size:16}):r.jsx(We,{size:16})}const er=window.__PEREGRINE_PLUGINS__,I="easy-configuration",R=`/api/plugins/${I}`;function rr(){var e;return((e=document.querySelector('meta[name="csrf-token"]'))==null?void 0:e.getAttribute("content"))??""}async function P(e,t={}){const a=await fetch(e,{...t,credentials:"same-origin",headers:{"Content-Type":"application/json",Accept:"application/json","X-CSRF-TOKEN":rr(),...t.headers}});if(!a.ok){const s=(await a.json().catch(()=>({}))).error;throw{status:a.status,code:s==null?void 0:s.code,message:s==null?void 0:s.message,messages:s==null?void 0:s.messages,fields:s==null?void 0:s.fields}}if(a.status!==204)return await a.json()}function A(){const{t:e,i18n:t}=Ce.useTranslation(I);return{t:e,lang:(t.language??"en").slice(0,2)}}function F(e,t,a=""){return e?e[t]??e.en??e.fr??Object.values(e)[0]??a:a}function B({size:e}){return r.jsx("span",{className:k("ec-spinner",e==="lg"&&"ec-spinner-lg"),"aria-hidden":!0})}function C({children:e,className:t,hover:a}){return r.jsx("div",{className:k("ec-card",a&&"ec-card-hover",t),children:e})}function L({variant:e="muted",children:t}){return r.jsx("span",{className:k("ec-badge",`ec-badge-${e}`),children:t})}function X({children:e}){return r.jsx("div",{className:"ec-empty",children:e})}function tr({tabs:e,active:t,onChange:a}){return r.jsx("div",{className:"ec-tabs",role:"tablist",children:e.map(n=>r.jsx("button",{type:"button",role:"tab","aria-selected":n.id===t,className:k("ec-tab",n.id===t&&"ec-tab-active"),onClick:()=>a(n.id),children:n.label},n.id))})}function ar({content:e,children:t}){return r.jsxs("span",{className:"ec-tooltip",tabIndex:0,children:[t,r.jsx("span",{role:"tooltip",className:"ec-tooltip-pop",children:e})]})}function O({variant:e="primary",size:t="md",loading:a=!1,disabled:n,type:s="button",className:c,children:l,...i}){return r.jsxs("button",{...i,type:s,disabled:n||a,className:k("ec-btn",`ec-btn-${e}`,t==="sm"&&"ec-btn-sm",c),children:[a&&r.jsx("span",{className:"ec-spinner","aria-hidden":!0}),l]})}function K({label:e,type:t="button",className:a,children:n,...s}){return r.jsx("button",{...s,type:t,"aria-label":e,title:e,className:k("ec-btn","ec-btn-icon",a),children:n})}function N({invalid:e,className:t,...a}){return r.jsx("input",{...a,className:k("ec-input",e&&"ec-input-invalid",t)})}function Q({invalid:e,className:t,...a}){return r.jsx("textarea",{...a,className:k("ec-textarea",e&&"ec-input-invalid",t)})}function or({value:e,onChange:t,children:a,className:n,disabled:s,invalid:c}){return r.jsx("select",{className:k("ec-select",c&&"ec-input-invalid",n),value:e,disabled:s,onChange:l=>t(l.target.value),children:a})}function pe({checked:e,onChange:t,disabled:a,label:n}){return r.jsx("button",{type:"button",role:"switch","aria-checked":e,"aria-label":n,disabled:a,className:k("ec-toggle",e&&"ec-toggle-on"),onClick:()=>t(!e),children:r.jsx("span",{className:"ec-toggle-knob"})})}const V=["ec-admin-templates"];function nr(){return E.useQuery({queryKey:V,queryFn:()=>P(`${R}/admin/templates`).then(e=>e.data)})}function sr(e){return E.useQuery({queryKey:["ec-admin-template",e],enabled:e!==null,queryFn:()=>P(`${R}/admin/templates/${e??""}`).then(t=>t.data)})}function ir(){return E.useQuery({queryKey:["ec-admin-eggs"],staleTime:5*6e4,queryFn:()=>P(`${R}/admin/eggs`).then(e=>e.data)})}function cr(){const e=E.useQueryClient();return E.useMutation({mutationFn:({id:t,template:a})=>t!==null?P(`${R}/admin/templates/${t}`,{method:"PUT",body:JSON.stringify({template:a})}):P(`${R}/admin/templates`,{method:"POST",body:JSON.stringify({template:a})}),onSuccess:()=>e.invalidateQueries({queryKey:V})})}function lr(){const e=E.useQueryClient();return E.useMutation({mutationFn:t=>P(`${R}/admin/templates/import`,{method:"POST",body:JSON.stringify({content:t})}),onSuccess:()=>e.invalidateQueries({queryKey:V})})}function dr(){const e=E.useQueryClient();return E.useMutation({mutationFn:t=>P(`${R}/admin/templates/${t}`,{method:"DELETE"}),onSuccess:()=>e.invalidateQueries({queryKey:V})})}function ur({value:e,onChange:t,eggs:a,loading:n}){const{t:s}=A(),[c,l]=h.useState(""),i=a.filter(d=>d.name.toLowerCase().includes(c.trim().toLowerCase())),u=d=>{t(e.includes(d)?e.filter(g=>g!==d):[...e,d])};return r.jsxs("div",{className:"ec-field-group",children:[r.jsx("label",{children:s("admin.editor.target_eggs")}),r.jsxs("div",{className:"ec-search",children:[r.jsx("span",{className:"ec-search-icon",children:r.jsx(He,{size:14})}),r.jsx(N,{value:c,placeholder:s("admin.editor.search_eggs"),onChange:d=>l(d.target.value)})]}),n?r.jsxs("div",{className:"ec-row ec-muted",children:[r.jsx(B,{})," ",s("common.loading")]}):r.jsxs("div",{className:"ec-egg-list",children:[i.map(d=>{const g=e.includes(d.id);return r.jsxs("button",{type:"button",className:k("ec-server-row",g&&"ec-server-row-on"),onClick:()=>u(d.id),children:[d.banner_image?r.jsx("img",{className:"ec-server-thumb",src:d.banner_image,alt:""}):r.jsx("span",{className:"ec-server-thumb"}),r.jsx("span",{className:"ec-grow ec-truncate",children:d.name}),r.jsxs("span",{className:"ec-muted",children:["#",d.id]}),g&&r.jsx(de,{size:16})]},d.id)}),i.length===0&&r.jsx("div",{className:"ec-empty",children:s("admin.editor.no_eggs")})]})]})}function mr({param:e,value:t,onChange:a,disabled:n,invalid:s}){const{lang:c}=A(),l=e.config;switch(e.display_type){case"boolean":return r.jsx(pr,{config:l,value:t,onChange:a,disabled:n});case"slider":return r.jsx(fr,{config:l,value:t,onChange:a,disabled:n,invalid:s});case"number":return r.jsx(N,{className:"ec-input-narrow",type:"number",min:l.min,max:l.max,step:l.step??(l.float?"any":1),value:t,disabled:n,invalid:s,onChange:i=>a(i.target.value)});case"select":return r.jsx(or,{value:t,disabled:n,invalid:s,onChange:a,children:(l.options??[]).map(i=>r.jsx("option",{value:i.value,children:F(i.label,c,i.value)},i.value))});case"multiselect":return r.jsx(gr,{config:l,value:t,onChange:a,disabled:n});case"textarea":return r.jsx(Q,{value:t,disabled:n,invalid:s,maxLength:l.max_length,onChange:i=>a(i.target.value)});case"color":return r.jsx(hr,{value:t,onChange:a,disabled:n,invalid:s});default:return r.jsx(N,{value:t,disabled:n,invalid:s,maxLength:l.max_length,onChange:i=>a(i.target.value)})}}function pr({config:e,value:t,onChange:a,disabled:n}){const s=e.true_value??"true",c=e.false_value??"false";return r.jsx(pe,{checked:t===s,disabled:n,onChange:l=>a(l?s:c)})}function fr({config:e,value:t,onChange:a,disabled:n,invalid:s}){const c=e.min??0,l=e.max??100,i=e.step??1;return r.jsxs("div",{className:"ec-slider-wrap",children:[r.jsx("input",{type:"range",className:"ec-slider",min:c,max:l,step:i,value:t,disabled:n,onChange:u=>a(u.target.value)}),r.jsx(N,{className:"ec-slider-number",type:"number",min:c,max:l,step:i,value:t,disabled:n,invalid:s,onChange:u=>a(u.target.value)})]})}function gr({config:e,value:t,onChange:a,disabled:n}){const{lang:s}=A(),c=e.separator&&e.separator!==""?e.separator:",",l=t.split(c).map(u=>u.trim()).filter(u=>u!==""),i=u=>{const d=l.includes(u)?l.filter(g=>g!==u):[...l,u];a(d.join(c))};return r.jsx("div",{className:"ec-chips",children:(e.options??[]).map(u=>r.jsx("button",{type:"button",disabled:n,className:k("ec-chip",l.includes(u.value)&&"ec-chip-on"),onClick:()=>i(u.value),children:F(u.label,s,u.value)},u.value))})}function hr({value:e,onChange:t,disabled:a,invalid:n}){const s=/^#[0-9a-fA-F]{6}$/.test(e)?e:`#${e}`,c=/^#[0-9a-fA-F]{6}$/.test(s)?s:"#000000";return r.jsxs("div",{className:"ec-color",children:[r.jsx("input",{type:"color",className:"ec-color-swatch",value:c,disabled:a,onChange:l=>t(l.target.value)}),r.jsx(N,{className:"ec-input-narrow",value:e,disabled:a,invalid:n,onChange:l=>t(l.target.value)})]})}function vr({param:e,value:t,onChange:a,disabled:n,dirty:s,saved:c,invalid:l,onReset:i,boost:u}){const{t:d,lang:g}=A(),_=F(e.label,g,e.key),j=F(e.description,g,""),v=e.config.default,f=i!==void 0&&v!==void 0&&!n&&t!==String(v);return r.jsxs("div",{className:k("ec-field",s&&"ec-field-dirty"),children:[r.jsxs("div",{className:"ec-field-label-col",children:[r.jsxs("span",{className:"ec-field-label",children:[_,j!==""&&r.jsx(ar,{content:j,children:r.jsx("span",{className:"ec-help",children:r.jsx($e,{size:13})})}),e.inferred&&r.jsx(L,{variant:"muted",children:d("field.auto_detected")})]}),j!==""&&r.jsx("span",{className:"ec-field-desc ec-truncate",children:j}),r.jsx("span",{className:"ec-field-desc ec-muted",children:e.section?`${e.section} · ${e.key}`:e.key})]}),r.jsxs("div",{className:"ec-field-control",children:[u,r.jsx(mr,{param:e,value:t,onChange:a,disabled:n,invalid:l}),c&&r.jsx("span",{className:"ec-field-saved","aria-hidden":!0,children:r.jsx(de,{size:15})}),f&&r.jsx(K,{label:d("field.reset_default"),className:"ec-reset",onClick:i,children:r.jsx(Ue,{size:14})})]})]})}function T(e){return typeof e=="object"&&e!==null&&!Array.isArray(e)}function fe(e,t,a){const n=T(a.config)?a.config:{},s=n.default;return{key:e,section:t,display_type:typeof a.display_type=="string"?a.display_type:"text",config:n,label:T(a.label)?a.label:null,description:T(a.description)?a.description:null,value:s===void 0?"":String(s),inferred:!1}}function xr(e){const t=[];if(!T(e))return{sectioned:!1,params:t};let a=!1;for(const[n,s]of Object.entries(e))if(T(s)&&"display_type"in s)t.push(fe(n,null,s));else if(T(s)){a=!0;for(const[c,l]of Object.entries(s))T(l)&&t.push(fe(c,n,l))}return{sectioned:a,params:t}}function br({files:e}){const{t,lang:a}=A(),[n,s]=h.useState({});return!Array.isArray(e)||e.length===0?r.jsx("div",{className:"ec-empty",children:t("admin.editor.preview_empty")}):r.jsx("div",{className:"ec-stack",children:e.map((c,l)=>{if(!T(c))return null;const{params:i}=xr(c.parameters),u=T(c.label)?F(c.label,a,String(c.path??"")):String(c.path??`file ${l+1}`);return r.jsxs("div",{className:"ec-section-group",children:[r.jsx("div",{className:"ec-section-head",children:u}),r.jsxs("div",{className:"ec-section-body",children:[i.map(d=>{const g=`${l}:${d.section??""}:${d.key}`;return r.jsx(vr,{param:d,value:n[g]??d.value,onChange:_=>s(j=>({...j,[g]:_}))},g)}),i.length===0&&r.jsx("div",{className:"ec-empty",children:t("admin.editor.no_params")})]})]},l)})})}function ge(e,t){const a={};return e.trim()!==""&&(a.en=e.trim()),t.trim()!==""&&(a.fr=t.trim()),a}function he(e){try{return JSON.parse(e)}catch{return null}}function ve({initial:e,isNew:t}){const{t:a}=A(),n=z.useNavigate(),s=me(),c=ir(),l=cr(),[i,u]=h.useState(e),[d,g]=h.useState("edit"),[_,j]=h.useState([]),v=p=>u(S=>({...S,...p})),f=()=>{const p=he(i.filesJson);if(p===null){j([a("admin.editor.invalid_files_json")]);return}const S={id:i.id,version:i.version===""?"1.0.0":i.version,name:ge(i.nameEn,i.nameFr),description:ge(i.descEn,i.descFr),author:i.author===""?null:i.author,target_eggs:i.targetEggs,boost:{enabled:i.boostEnabled,parameter_blacklist:i.blacklist.split(",").map(M=>M.trim()).filter(M=>M!=="")},files:p};j([]),l.mutate({id:t?null:i.id,template:S},{onSuccess:()=>{s.success(a("admin.editor.saved")),n(`/plugins/${I}`)},onError:M=>{const U=M;j(U.messages??[U.message??a("errors.generic")]),s.error(a("admin.editor.save_failed"))}})};return r.jsxs("div",{className:"ec-page",children:[r.jsxs("div",{className:"ec-between",children:[r.jsxs("div",{className:"ec-row",children:[r.jsxs(O,{variant:"ghost",onClick:()=>n(`/plugins/${I}`),children:[r.jsx(Fe,{size:15})," ",a("common.back")]}),r.jsx("h1",{className:"ec-title",children:t?a("admin.editor.title_new"):i.id})]}),r.jsxs(O,{loading:l.isPending,onClick:f,children:[r.jsx(Ge,{size:15})," ",a("common.save")]})]}),_.length>0&&r.jsx(C,{children:r.jsx("ul",{className:"ec-error-list",children:_.map(p=>r.jsx("li",{children:p},p))})}),r.jsx(tr,{active:d,onChange:p=>g(p),tabs:[{id:"edit",label:a("admin.editor.tab_edit")},{id:"preview",label:a("admin.editor.tab_preview")}]}),d==="edit"?r.jsxs("div",{className:"ec-stack",children:[r.jsx(C,{children:r.jsxs("div",{className:"ec-stack",children:[r.jsxs("div",{className:"ec-field-group",children:[r.jsx("label",{children:a("admin.editor.id")}),r.jsx(N,{value:i.id,disabled:!t,placeholder:"minecraft-vanilla",onChange:p=>v({id:p.target.value})})]}),r.jsxs("div",{className:"ec-cols-2",children:[r.jsxs("div",{className:"ec-field-group",children:[r.jsx("label",{children:a("admin.editor.name_en")}),r.jsx(N,{value:i.nameEn,onChange:p=>v({nameEn:p.target.value})})]}),r.jsxs("div",{className:"ec-field-group",children:[r.jsx("label",{children:a("admin.editor.name_fr")}),r.jsx(N,{value:i.nameFr,onChange:p=>v({nameFr:p.target.value})})]})]}),r.jsxs("div",{className:"ec-cols-2",children:[r.jsxs("div",{className:"ec-field-group",children:[r.jsx("label",{children:a("admin.editor.desc_en")}),r.jsx(N,{value:i.descEn,onChange:p=>v({descEn:p.target.value})})]}),r.jsxs("div",{className:"ec-field-group",children:[r.jsx("label",{children:a("admin.editor.desc_fr")}),r.jsx(N,{value:i.descFr,onChange:p=>v({descFr:p.target.value})})]})]}),r.jsxs("div",{className:"ec-field-group",children:[r.jsx("label",{children:a("admin.editor.author")}),r.jsx(N,{value:i.author,onChange:p=>v({author:p.target.value})})]})]})}),r.jsx(C,{children:r.jsx(ur,{value:i.targetEggs,onChange:p=>v({targetEggs:p}),eggs:c.data??[],loading:c.isLoading})}),r.jsx(C,{children:r.jsxs("div",{className:"ec-stack",children:[r.jsxs("div",{className:"ec-between",children:[r.jsx("span",{className:"ec-field-label",children:a("admin.editor.boost_enabled")}),r.jsx(pe,{checked:i.boostEnabled,onChange:p=>v({boostEnabled:p}),label:a("admin.editor.boost_enabled")})]}),i.boostEnabled&&r.jsxs("div",{className:"ec-field-group",children:[r.jsx("label",{children:a("admin.editor.boost_blacklist")}),r.jsx(N,{value:i.blacklist,placeholder:"server-port, rcon.port",onChange:p=>v({blacklist:p.target.value})}),r.jsx("span",{className:"ec-field-desc ec-muted",children:a("admin.editor.boost_blacklist_hint")})]})]})}),r.jsx(C,{children:r.jsxs("div",{className:"ec-field-group",children:[r.jsx("label",{children:a("admin.editor.files_json")}),r.jsx(Q,{className:"ec-mono",value:i.filesJson,spellCheck:!1,onChange:p=>v({filesJson:p.target.value})})]})})]}):r.jsx(br,{files:he(i.filesJson)})]})}const yr=`[
  {
    "id": "server-properties",
    "path": "server.properties",
    "format": "properties",
    "enabled": true,
    "label": { "en": "Server properties", "fr": "Propriétés serveur" },
    "parameters": {
      "max-players": {
        "display_type": "slider",
        "config": { "min": 1, "max": 100, "step": 1 },
        "label": { "en": "Max players", "fr": "Joueurs max" }
      }
    }
  }
]`;function q(e){return typeof e=="object"&&e!==null&&!Array.isArray(e)}function $(e,t=""){return typeof e=="string"?e:t}function xe(){return{id:"",version:"1.0.0",nameEn:"",nameFr:"",descEn:"",descFr:"",author:"",targetEggs:[],boostEnabled:!1,blacklist:"",filesJson:yr}}function jr(e,t){if(t===null)return{...xe(),id:e};const a=q(t.name)?t.name:{},n=q(t.description)?t.description:{},s=q(t.boost)?t.boost:{},c=Array.isArray(s.parameter_blacklist)?s.parameter_blacklist.map(String):[],l=Array.isArray(t.target_eggs)?t.target_eggs.filter(i=>typeof i=="number"):[];return{id:e,version:$(t.version,"1.0.0"),nameEn:$(a.en),nameFr:$(a.fr),descEn:$(n.en),descFr:$(n.fr),author:$(t.author),targetEggs:l,boostEnabled:s.enabled===!0,blacklist:c.join(", "),filesJson:JSON.stringify(t.files??[],null,2)}}function be(){var s;const{t:e}=A(),{templateId:t}=z.useParams(),a=t===void 0,n=sr(a?null:t??null);return a?r.jsx(ve,{initial:xe(),isNew:!0}):n.isError&&((s=n.error)==null?void 0:s.status)===403?r.jsx("div",{className:"ec-page",children:r.jsx(C,{children:r.jsx(X,{children:e("admin.unauthorized")})})}):n.isLoading||n.data===void 0?r.jsx("div",{className:"ec-page",children:r.jsxs("div",{className:"ec-row ec-muted",children:[r.jsx(B,{})," ",e("common.loading")]})}):r.jsx(ve,{initial:jr(n.data.id,n.data.definition),isNew:!1},n.data.id)}function wr({open:e,onClose:t,title:a,children:n,footer:s,size:c="md",closeLabel:l}){return h.useEffect(()=>{if(!e)return;const i=u=>{u.key==="Escape"&&t()};return window.addEventListener("keydown",i),()=>window.removeEventListener("keydown",i)},[e,t]),e?r.jsx("div",{className:"ec-scrim",onMouseDown:i=>{i.target===i.currentTarget&&t()},children:r.jsxs("div",{className:k("ec-dialog",c==="lg"&&"ec-dialog-lg"),role:"dialog","aria-modal":"true",children:[r.jsxs("div",{className:"ec-dialog-head",children:[r.jsx("p",{className:"ec-dialog-title ec-grow",children:a}),r.jsx(K,{label:l,onClick:t,children:r.jsx(Qe,{size:16})})]}),n,s!==void 0&&r.jsx("div",{className:"ec-dialog-foot",children:s})]})}):null}function kr(){var v;const{t:e,lang:t}=A(),a=z.useNavigate(),n=me(),s=nr(),c=dr(),l=lr(),[i,u]=h.useState(!1),[d,g]=h.useState("");if(s.isError&&((v=s.error)==null?void 0:v.status)===403)return r.jsx("div",{className:"ec-page",children:r.jsx(C,{children:r.jsx(X,{children:e("admin.unauthorized")})})});const _=f=>{window.confirm(e("admin.list.confirm_delete",{id:f}))&&c.mutate(f,{onSuccess:()=>n.success(e("admin.list.deleted")),onError:()=>n.error(e("errors.generic"))})},j=()=>{l.mutate(d,{onSuccess:()=>{n.success(e("admin.list.imported")),u(!1),g("")},onError:f=>n.error(f.message??e("admin.list.import_failed"))})};return r.jsxs("div",{className:"ec-page",children:[r.jsxs("div",{className:"ec-between",children:[r.jsxs("div",{children:[r.jsx("h1",{className:"ec-title",children:e("admin.list.title")}),r.jsx("p",{className:"ec-subtitle",children:e("admin.list.subtitle")})]}),r.jsxs("div",{className:"ec-row",children:[r.jsxs(O,{variant:"secondary",onClick:()=>u(!0),children:[r.jsx(Ke,{size:15})," ",e("admin.list.import")]}),r.jsxs(O,{onClick:()=>a(`/plugins/${I}/new`),children:[r.jsx(Je,{size:15})," ",e("admin.list.new")]})]})]}),s.isLoading?r.jsxs("div",{className:"ec-row ec-muted",children:[r.jsx(B,{})," ",e("common.loading")]}):s.data&&s.data.length>0?r.jsx("div",{className:"ec-grid",children:s.data.map(f=>r.jsxs(C,{hover:!0,className:"ec-template-card",children:[r.jsxs("div",{className:"ec-between",children:[r.jsx("strong",{className:"ec-truncate",children:F(f.name,t,f.template_id)}),f.is_valid?r.jsxs(L,{variant:"success",children:["v",f.version]}):r.jsx(L,{variant:"warning",children:e("admin.list.invalid")})]}),r.jsx("span",{className:"ec-subtitle ec-truncate",children:f.template_id}),!f.is_valid&&f.last_error!==null&&r.jsx("span",{className:"ec-field-desc ec-truncate",children:f.last_error}),r.jsxs("div",{className:"ec-template-card-foot",children:[r.jsx(L,{variant:"muted",children:e("admin.list.files",{count:f.file_count})}),r.jsx(L,{variant:"muted",children:e("admin.list.eggs",{count:f.target_eggs.length})}),f.boost_enabled&&r.jsx(L,{variant:"accent",children:e("admin.list.boost")})]}),r.jsxs("div",{className:"ec-row",children:[r.jsxs(O,{size:"sm",variant:"secondary",onClick:()=>a(`/plugins/${I}/${f.template_id}`),children:[r.jsx(Ve,{size:13})," ",e("common.edit")]}),r.jsxs("a",{className:"ec-btn ec-btn-ghost ec-btn-sm",href:`${R}/admin/templates/${f.template_id}/export`,children:[r.jsx(De,{size:13})," ",e("common.export")]}),r.jsx(K,{label:e("common.delete"),onClick:()=>_(f.template_id),children:r.jsx(Be,{size:14})})]})]},f.template_id))}):r.jsx(C,{children:r.jsx(X,{children:e("admin.list.empty")})}),r.jsx(wr,{open:i,onClose:()=>u(!1),closeLabel:e("common.close"),title:e("admin.list.import_title"),footer:r.jsxs(r.Fragment,{children:[r.jsx(O,{variant:"ghost",onClick:()=>u(!1),children:e("common.cancel")}),r.jsx(O,{loading:l.isPending,onClick:j,children:e("admin.list.import")})]}),children:r.jsx("div",{className:"ec-dialog-body",children:r.jsx(Q,{className:"ec-mono",value:d,placeholder:'{ "id": "minecraft-vanilla", ... }',onChange:f=>g(f.target.value)})})})]})}function _r(){return r.jsx(qe,{children:r.jsx("div",{className:"ec-root",children:r.jsxs(z.Routes,{children:[r.jsx(z.Route,{path:"",element:r.jsx(kr,{})}),r.jsx(z.Route,{path:"new",element:r.jsx(be,{})}),r.jsx(z.Route,{path:":templateId",element:r.jsx(be,{})})]})})})}const Nr=`
.ec-page { max-width: var(--layout-container-max, 1100px); margin: 0 auto; padding: 1.5rem; display: flex; flex-direction: column; gap: 1.25rem; }
.ec-grid { display: grid; gap: 0.85rem; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); }
.ec-list { display: flex; flex-direction: column; gap: 0.5rem; }
.ec-egg-list { display: flex; flex-direction: column; gap: 0.4rem; max-height: 18rem; overflow-y: auto; padding-right: 0.25rem; }
.ec-mono { font-family: var(--font-mono); font-size: 0.78rem; line-height: 1.5; min-height: 22rem; white-space: pre; tab-size: 2; }
.ec-field-group { display: flex; flex-direction: column; gap: 0.35rem; }
.ec-field-group > label { font-size: 0.75rem; font-weight: 600; color: var(--color-text-secondary); }
.ec-cols-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.ec-divider { height: 1px; background: var(--color-border); border: none; margin: 0.25rem 0; }
.ec-error-list { margin: 0; padding-left: 1.1rem; color: var(--color-danger); font-size: 0.78rem; display: flex; flex-direction: column; gap: 0.2rem; }
.ec-template-card { display: flex; flex-direction: column; gap: 0.6rem; }
.ec-template-card-foot { display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap; }
@media (max-width: 640px) { .ec-cols-2 { grid-template-columns: 1fr; } }
`,Er=`
.ec-root {
    font-family: var(--font-sans);
    color: var(--color-text-primary);
    font-size: 0.875rem;
    line-height: 1.5;
}
.ec-root *, .ec-root *::before, .ec-root *::after { box-sizing: border-box; }

.ec-stack { display: flex; flex-direction: column; gap: 1.25rem; }
.ec-row { display: flex; align-items: center; gap: 0.625rem; }
.ec-between { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap; }
.ec-grow { flex: 1 1 auto; min-width: 0; }
.ec-muted { color: var(--color-text-muted); }
.ec-secondary { color: var(--color-text-secondary); }
.ec-truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.ec-title { font-size: 1.125rem; font-weight: 700; margin: 0; line-height: 1.3; }
.ec-subtitle { font-size: 0.8125rem; color: var(--color-text-muted); margin: 0; }
.ec-section-label { font-size: 0.6875rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-text-muted); margin: 0; }

.ec-card {
    border-radius: var(--radius-lg);
    border: 1px solid var(--color-border);
    background: var(--color-surface);
    padding: 1rem;
    transition: border-color var(--transition-base), box-shadow var(--transition-base);
}
.ec-card-hover:hover { border-color: var(--color-border-hover); box-shadow: var(--shadow-md); }

.ec-icon-box {
    width: 40px; height: 40px; flex-shrink: 0;
    border-radius: var(--radius-lg);
    background: rgba(var(--color-primary-rgb), 0.1);
    color: var(--color-primary);
    display: flex; align-items: center; justify-content: center;
}

.ec-btn {
    appearance: none; border: none; cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem;
    padding: 0.5rem 0.9rem; font-size: 0.8125rem; font-weight: 600; font-family: inherit;
    border-radius: var(--radius);
    transition: background var(--transition-fast), opacity var(--transition-fast), transform var(--transition-fast), box-shadow var(--transition-fast);
}
.ec-btn:active { transform: scale(0.97); }
.ec-btn:disabled { opacity: 0.45; cursor: not-allowed; }
.ec-btn:focus-visible { outline: none; box-shadow: 0 0 0 2px var(--color-background), 0 0 0 4px var(--color-ring); }
.ec-btn-primary { background: var(--color-primary); color: #fff; box-shadow: 0 2px 8px var(--color-primary-glow); }
.ec-btn-primary:hover:not(:disabled) { background: var(--color-primary-hover); box-shadow: 0 0 20px var(--color-primary-glow); }
.ec-btn-secondary { background: var(--color-surface); color: var(--color-text-primary); border: 1px solid var(--color-border-hover); }
.ec-btn-secondary:hover:not(:disabled) { background: var(--color-surface-hover); border-color: var(--color-text-secondary); }
.ec-btn-ghost { background: transparent; color: var(--color-text-secondary); font-weight: 500; }
.ec-btn-ghost:hover:not(:disabled) { background: var(--color-surface-hover); color: var(--color-text-primary); }
.ec-btn-danger { background: rgba(var(--color-danger-rgb), 0.12); color: var(--color-danger); border: 1px solid rgba(var(--color-danger-rgb), 0.2); }
.ec-btn-danger:hover:not(:disabled) { background: rgba(var(--color-danger-rgb), 0.2); }
.ec-btn-sm { padding: 0.35rem 0.6rem; font-size: 0.75rem; }
.ec-btn-icon { padding: 0; width: 32px; height: 32px; background: transparent; color: var(--color-text-secondary); border: 1px solid var(--color-border); }
.ec-btn-icon:hover:not(:disabled) { background: var(--color-surface-hover); color: var(--color-text-primary); }

.ec-input, .ec-select, .ec-textarea {
    width: 100%; font-family: inherit; font-size: 0.8125rem; color: var(--color-text-primary);
    background: var(--color-background); border: 1px solid var(--color-border);
    border-radius: var(--radius); padding: 0.5rem 0.7rem; outline: none;
    transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
}
.ec-textarea { resize: vertical; min-height: 4.5rem; line-height: 1.45; }
.ec-input:focus, .ec-select:focus, .ec-textarea:focus { border-color: var(--color-primary); box-shadow: 0 0 0 3px var(--color-primary-glow); }
.ec-input-invalid { border-color: var(--color-danger); }
.ec-select { cursor: pointer; appearance: none; padding-right: 1.9rem;
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%238b849e' stroke-width='2'><polyline points='6 9 12 15 18 9'/></svg>");
    background-repeat: no-repeat; background-position: right 0.6rem center; }

.ec-badge { display: inline-flex; align-items: center; gap: 0.25rem; border-radius: var(--radius-full);
    padding: 0.1rem 0.55rem; font-size: 0.6875rem; font-weight: 600; line-height: 1.4; white-space: nowrap; }
.ec-badge-accent { background: rgba(var(--color-accent-rgb), 0.15); color: var(--color-accent); }
.ec-badge-info { background: rgba(var(--color-info-rgb), 0.15); color: var(--color-info); }
.ec-badge-warning { background: rgba(var(--color-warning-rgb), 0.15); color: var(--color-warning); }
.ec-badge-success { background: rgba(var(--color-success-rgb), 0.15); color: var(--color-success); }
.ec-badge-muted { background: var(--surface-overlay-soft); color: var(--color-text-secondary); }

.ec-spinner { width: 1rem; height: 1rem; border-radius: var(--radius-full);
    border: 2px solid var(--surface-overlay-strong); border-top-color: var(--color-primary);
    animation: ec-spin 0.7s linear infinite; display: inline-block; }
.ec-spinner-lg { width: 1.75rem; height: 1.75rem; border-width: 3px; }

.ec-tabs { display: flex; gap: 0.25rem; border-bottom: 1px solid var(--color-border); }
.ec-tab { appearance: none; border: none; background: transparent; cursor: pointer; font-family: inherit;
    padding: 0.5rem 0.85rem; font-size: 0.8125rem; font-weight: 500; color: var(--color-text-secondary);
    border-bottom: 2px solid transparent; margin-bottom: -1px; transition: color var(--transition-fast), border-color var(--transition-fast); }
.ec-tab:hover { color: var(--color-text-primary); }
.ec-tab-active { color: var(--color-primary); border-bottom-color: var(--color-primary); }

.ec-empty { padding: 2.5rem 1rem; text-align: center; color: var(--color-text-muted); font-size: 0.8125rem; }

@keyframes ec-spin { to { transform: rotate(360deg); } }
@keyframes ec-fade-in { from { opacity: 0; } to { opacity: 1; } }
@keyframes ec-pop-in { from { opacity: 0; transform: translateY(6px) scale(0.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
@keyframes ec-slide-up { from { opacity: 0; transform: translate(-50%, 1rem); } to { opacity: 1; transform: translate(-50%, 0); } }
`,Cr=`
.ec-section-group { border: 1px solid var(--color-border); border-radius: var(--radius-lg); overflow: hidden; background: var(--color-surface); }
.ec-section-head { display: flex; align-items: center; gap: 0.5rem; width: 100%; padding: 0.75rem 1rem;
    background: transparent; border: none; cursor: pointer; font-family: inherit; color: var(--color-text-primary);
    font-size: 0.8125rem; font-weight: 600; text-align: left; }
.ec-section-head:hover { background: var(--color-surface-hover); }
.ec-section-chevron { transition: transform var(--transition-base); color: var(--color-text-muted); display: inline-flex; }
.ec-section-collapsed .ec-section-chevron { transform: rotate(-90deg); }
.ec-section-body { display: flex; flex-direction: column; }
.ec-section-count { margin-left: auto; font-size: 0.6875rem; color: var(--color-text-muted); font-weight: 500; }

.ec-field { display: flex; align-items: center; gap: 1rem; padding: 0.85rem 1rem; border-top: 1px solid var(--color-border); position: relative; }
.ec-field:first-child { border-top: none; }
.ec-field-dirty { box-shadow: inset 3px 0 0 var(--color-accent); }
.ec-field-label-col { display: flex; flex-direction: column; gap: 0.15rem; min-width: 0; flex: 1 1 50%; }
.ec-field-label { font-weight: 500; display: inline-flex; align-items: center; gap: 0.4rem; }
.ec-field-desc { font-size: 0.75rem; color: var(--color-text-muted); }
.ec-field-control { flex: 1 1 45%; display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem; min-width: 0; }
.ec-field-value { color: var(--color-accent); font-weight: 600; font-variant-numeric: tabular-nums; }
.ec-field-inferred { font-style: italic; }
.ec-field-saved { color: var(--color-success); display: inline-flex; animation: ec-fade-in var(--transition-base); }

.ec-help { color: var(--color-text-muted); display: inline-flex; cursor: help; }

.ec-reset { opacity: 0; transition: opacity var(--transition-fast); }
.ec-field:hover .ec-reset { opacity: 1; }

/* Toggle */
.ec-toggle { position: relative; width: 2.5rem; height: 1.4rem; border-radius: var(--radius-full); border: none; cursor: pointer;
    background: var(--color-border-hover); transition: background var(--transition-base); flex-shrink: 0; }
.ec-toggle-on { background: var(--color-primary); }
.ec-toggle-knob { position: absolute; top: 2px; left: 2px; width: calc(1.4rem - 4px); height: calc(1.4rem - 4px);
    border-radius: var(--radius-full); background: #fff; transition: transform var(--transition-base); }
.ec-toggle-on .ec-toggle-knob { transform: translateX(1.1rem); }
.ec-toggle:disabled { opacity: 0.5; cursor: not-allowed; }

/* Slider */
.ec-slider-wrap { display: flex; align-items: center; gap: 0.75rem; width: 100%; }
.ec-slider { -webkit-appearance: none; appearance: none; flex: 1 1 auto; height: 0.35rem; border-radius: var(--radius-full);
    background: var(--color-border-hover); outline: none; cursor: pointer; }
.ec-slider::-webkit-slider-thumb { -webkit-appearance: none; appearance: none; width: 1rem; height: 1rem; border-radius: var(--radius-full);
    background: var(--color-primary); border: 2px solid var(--color-surface); box-shadow: var(--shadow-sm); cursor: pointer; }
.ec-slider::-moz-range-thumb { width: 1rem; height: 1rem; border-radius: var(--radius-full); background: var(--color-primary);
    border: 2px solid var(--color-surface); cursor: pointer; }
.ec-slider-number { width: 5rem; flex-shrink: 0; text-align: right; }
.ec-input-narrow { max-width: 8rem; }

/* Color */
.ec-color { display: flex; align-items: center; gap: 0.5rem; }
.ec-color-swatch { width: 1.6rem; height: 1.6rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border); padding: 0; cursor: pointer; background: none; }

/* Search */
.ec-search { position: relative; }
.ec-search .ec-input { padding-left: 2rem; }
.ec-search-icon { position: absolute; left: 0.6rem; top: 50%; transform: translateY(-50%); color: var(--color-text-muted); pointer-events: none; }

/* Multiselect chips */
.ec-chips { display: flex; flex-wrap: wrap; gap: 0.35rem; justify-content: flex-end; }
.ec-chip { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.15rem 0.5rem; border-radius: var(--radius-full);
    font-size: 0.7rem; cursor: pointer; border: 1px solid var(--color-border); background: var(--color-background); color: var(--color-text-secondary); transition: all var(--transition-fast); }
.ec-chip-on { background: rgba(var(--color-primary-rgb), 0.15); border-color: var(--color-primary); color: var(--color-primary); }
`,Tr=`
.ec-scrim { position: fixed; inset: 0; z-index: 60; background: var(--modal-scrim);
    backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; padding: 1rem;
    animation: ec-fade-in var(--transition-fast); }
.ec-dialog { width: 100%; max-width: 560px; max-height: calc(100vh - 2rem); overflow-y: auto;
    border-radius: var(--radius-lg); border: 1px solid var(--color-border); background: var(--color-surface);
    box-shadow: var(--shadow-lg); display: flex; flex-direction: column; animation: ec-pop-in var(--transition-base); }
.ec-dialog-lg { max-width: 760px; }
.ec-dialog-head { display: flex; align-items: center; gap: 0.6rem; padding: 1rem 1.25rem; border-bottom: 1px solid var(--color-border); }
.ec-dialog-title { font-size: 0.95rem; font-weight: 700; margin: 0; }
.ec-dialog-body { padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem; }
.ec-dialog-foot { display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem;
    padding: 1rem 1.25rem; border-top: 1px solid var(--color-border); }

.ec-steps { display: flex; align-items: center; gap: 0.4rem; }
.ec-step-dot { width: 1.5rem; height: 1.5rem; border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center;
    font-size: 0.7rem; font-weight: 600; background: var(--surface-overlay-soft); color: var(--color-text-muted); }
.ec-step-dot-active { background: var(--color-primary); color: #fff; }
.ec-step-bar { flex: 1; height: 2px; background: var(--color-border); }

.ec-save-bar { position: fixed; bottom: 1.5rem; left: 50%; transform: translateX(-50%); z-index: 50;
    display: flex; align-items: center; gap: 0.85rem; padding: 0.6rem 0.7rem 0.6rem 1.1rem;
    border-radius: var(--radius-full); border: 1px solid var(--color-glass-border);
    background: var(--color-glass); backdrop-filter: var(--glass-blur); box-shadow: var(--shadow-lg);
    animation: ec-slide-up var(--transition-smooth); }
.ec-save-bar-text { font-size: 0.8125rem; font-weight: 500; }

.ec-overlay { position: absolute; inset: 0; z-index: 20; border-radius: var(--radius-lg);
    background: var(--ambient-overlay); backdrop-filter: blur(2px);
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.85rem; text-align: center; padding: 1.5rem; }
.ec-overlay-card { display: flex; flex-direction: column; align-items: center; gap: 0.85rem; max-width: 22rem; }
.ec-relative { position: relative; }

.ec-toast-host { position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 80; display: flex; flex-direction: column; gap: 0.5rem; max-width: 22rem; }
.ec-toast { display: flex; align-items: flex-start; gap: 0.5rem; padding: 0.7rem 0.85rem; border-radius: var(--radius);
    border: 1px solid var(--color-border); background: var(--color-glass); backdrop-filter: var(--glass-blur);
    box-shadow: var(--shadow-md); font-size: 0.8125rem; animation: ec-pop-in var(--transition-base); }
.ec-toast-error { border-color: rgba(var(--color-danger-rgb), 0.4); }
.ec-toast-success { border-color: rgba(var(--color-success-rgb), 0.4); }
.ec-toast-icon { flex-shrink: 0; margin-top: 0.05rem; }
.ec-toast-error .ec-toast-icon { color: var(--color-danger); }
.ec-toast-success .ec-toast-icon { color: var(--color-success); }
.ec-toast-warning .ec-toast-icon { color: var(--color-warning); }

.ec-tooltip { position: relative; display: inline-flex; }
.ec-tooltip-pop { position: absolute; bottom: calc(100% + 0.4rem); left: 50%; transform: translateX(-50%);
    background: var(--color-surface-elevated); color: var(--color-text-primary); border: 1px solid var(--color-border);
    border-radius: var(--radius-sm); padding: 0.4rem 0.6rem; font-size: 0.7rem; font-weight: 400; white-space: normal; width: max-content; max-width: 16rem;
    box-shadow: var(--shadow-md); z-index: 70; opacity: 0; pointer-events: none; transition: opacity var(--transition-fast); }
.ec-tooltip:hover .ec-tooltip-pop, .ec-tooltip:focus-within .ec-tooltip-pop { opacity: 1; }

.ec-server-row { display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 0.75rem; border-radius: var(--radius);
    border: 1px solid var(--color-border); cursor: pointer; transition: all var(--transition-fast); background: var(--color-background); }
.ec-server-row:hover:not(.ec-server-row-disabled) { border-color: var(--color-border-hover); }
.ec-server-row-on { border-color: var(--color-primary); background: rgba(var(--color-primary-rgb), 0.08); }
.ec-server-row-disabled { opacity: 0.5; cursor: not-allowed; }
.ec-server-thumb { width: 2.5rem; height: 2.5rem; border-radius: var(--radius); object-fit: cover; flex-shrink: 0; background: var(--color-surface-elevated); }
`,ye="easy-config-styles";function Sr(){if(typeof document>"u"||document.getElementById(ye))return;const e=document.createElement("style");e.id=ye,e.textContent=[Er,Cr,Tr,Nr].join(`
`),document.head.appendChild(e)}Sr(),er.register("easy-configuration",_r)})(window.__PEREGRINE_SHARED__.React,window.__PEREGRINE_SHARED__.ReactRouterDom,window.__PEREGRINE_SHARED__,window.__PEREGRINE_SHARED__.ReactQuery);
