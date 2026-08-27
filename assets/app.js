(function(){
  const select=document.getElementById('langMode');
  function setMode(m){document.body.classList.remove('lang-mode-en','lang-mode-np','lang-mode-both');document.body.classList.add('lang-mode-'+m);localStorage.setItem('langMode',m);if(select)select.value=m;}
  setMode(localStorage.getItem('langMode')||'both'); if(select)select.addEventListener('change',e=>setMode(e.target.value));
  document.querySelectorAll('input[type=file][data-preview]').forEach(inp=>inp.addEventListener('change',()=>{const img=document.getElementById(inp.dataset.preview);const f=inp.files&&inp.files[0];if(img&&f){img.src=URL.createObjectURL(f);img.style.display='block';}}));
  document.querySelectorAll('[data-confirm]').forEach(el=>el.addEventListener('click',e=>{if(!confirm(el.dataset.confirm))e.preventDefault();}));
})();
