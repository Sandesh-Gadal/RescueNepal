(function(){
  const select=document.getElementById('langMode');
  function setMode(m){document.body.classList.remove('lang-mode-en','lang-mode-np','lang-mode-both');document.body.classList.add('lang-mode-'+m);localStorage.setItem('langMode',m);if(select)select.value=m;}
  setMode(localStorage.getItem('langMode')||'en'); if(select)select.addEventListener('change',e=>setMode(e.target.value));
  document.querySelectorAll('input[type=file][accept*="image"]').forEach(inp=>inp.addEventListener('change',()=>{
    const img=inp.dataset.preview?document.getElementById(inp.dataset.preview):null;
    const files=inp.files?Array.from(inp.files):[];
    const isHeic=f=>/^image\/hei[cf]/i.test(f.type)||/\.hei[cf]$/i.test(f.name);
    let warn=inp.parentElement.querySelector('.heic-warning');
    if(files.some(isHeic)){
      if(!warn){warn=document.createElement('div');warn.className='heic-warning hint';warn.style.color='#b42318';inp.insertAdjacentElement('afterend',warn);}
      warn.textContent='This is an iPhone HEIC photo, which cannot be uploaded here. On the iPhone: open the photo in Photos, use Share → Copy/Duplicate as JPEG (or set Settings → Camera → Formats → "Most Compatible") and upload the JPEG version instead.';
      inp.value='';
      if(img)img.style.display='none';
      return;
    }
    if(warn)warn.textContent='';
    const f=files[0];
    if(img&&f){img.src=URL.createObjectURL(f);img.style.display='block';}
  }));
  document.querySelectorAll('[data-confirm]').forEach(el=>el.addEventListener('click',e=>{if(!confirm(el.dataset.confirm))e.preventDefault();}));
})();
