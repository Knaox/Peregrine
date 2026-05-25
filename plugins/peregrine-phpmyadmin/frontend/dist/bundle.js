(function(l,_){"use strict";let p=!1;function v(){if(p||typeof document>"u")return;p=!0;const t=document.createElement("style");t.id="pma-plugin-styles",t.textContent=`
.pma-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.75rem;
    border-radius: var(--radius, 0.5rem);
    border: 1px solid var(--color-border, rgba(255,255,255,0.12));
    background: var(--color-surface-hover, rgba(255,255,255,0.05));
    color: var(--color-text-primary, #f8fafc);
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1;
    cursor: pointer;
    transition: border-color .15s ease, background .15s ease;
}
.pma-btn:hover:not(:disabled) { border-color: var(--color-primary, #f97316); }
.pma-btn:disabled { opacity: .5; cursor: not-allowed; }
.pma-btn svg { width: 0.95rem; height: 0.95rem; flex: none; }
`,document.head.appendChild(t)}var u={exports:{}},a={};/**
 * @license React
 * react-jsx-runtime.production.js
 *
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */var m;function E(){if(m)return a;m=1;var t=Symbol.for("react.transitional.element"),i=Symbol.for("react.fragment");function n(o,e,r){var c=null;if(r!==void 0&&(c=""+r),e.key!==void 0&&(c=""+e.key),"key"in e){r={};for(var d in e)d!=="key"&&(r[d]=e[d])}else r=e;return e=r.ref,{$$typeof:t,type:o,key:c,ref:e!==void 0?e:null,props:r}}return a.Fragment=i,a.jsx=n,a.jsxs=n,a}var f;function y(){return f||(f=1,u.exports=E()),u.exports}var s=y();const x=window.__PEREGRINE_PLUGINS__,h="peregrine-phpmyadmin",R=`/api/plugins/${h}`;function g(){var t;return((t=document.querySelector('meta[name="csrf-token"]'))==null?void 0:t.getAttribute("content"))??""}async function b(t,i={}){var o;const n=await fetch(t,{...i,credentials:"same-origin",headers:{"Content-Type":"application/json",Accept:"application/json","X-CSRF-TOKEN":g(),...i.headers}});if(!n.ok){const e=await n.json().catch(()=>({}));throw{status:n.status,message:((o=e.error)==null?void 0:o.message)??e.message}}if(n.status!==204)return await n.json()}function j(){const{t}=_.useTranslation(h);return t}function w(){return s.jsxs("svg",{viewBox:"0 0 24 24",fill:"none",stroke:"currentColor",strokeWidth:"1.6",strokeLinecap:"round",strokeLinejoin:"round",children:[s.jsx("ellipse",{cx:"12",cy:"5",rx:"8",ry:"3"}),s.jsx("path",{d:"M4 5v14c0 1.66 3.58 3 8 3s8-1.34 8-3V5"}),s.jsx("path",{d:"M4 12c0 1.66 3.58 3 8 3s8-1.34 8-3"})]})}function k({serverId:t,database:i}){const n=j(),{data:o}=l.useQuery({queryKey:["pma","state"],queryFn:()=>b(`${R}/state`),staleTime:3e5}),e=l.useMutation({mutationFn:()=>b(`${R}/servers/${t}/databases/${i.id}/launch`,{method:"POST"}),onSuccess:({url:r})=>{window.open(r,"_blank","noopener,noreferrer")}});return o!=null&&o.enabled?s.jsxs("button",{type:"button",className:"pma-btn",disabled:e.isPending,onClick:()=>e.mutate(),title:n("button.open_title",{name:i.name}),children:[s.jsx(w,{}),s.jsx("span",{children:e.isPending?n("button.opening"):n("button.open")})]}):null}v(),typeof x.registerDatabaseRowAction=="function"&&x.registerDatabaseRowAction("phpmyadmin",k)})(window.__PEREGRINE_SHARED__.ReactQuery,window.__PEREGRINE_SHARED__);
