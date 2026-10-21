"use strict";
document.addEventListener("DOMContentLoaded",()=>{
  lucide.createIcons();
  const dialog=document.getElementById("image-dialog");
  const image=document.getElementById("dialog-image");
  const caption=document.getElementById("dialog-caption");
  let trigger=null;
  document.querySelectorAll(".image-button").forEach(button=>button.addEventListener("click",()=>{
    trigger=button;image.src=button.dataset.image;
    image.alt=button.dataset.caption;caption.textContent=button.dataset.caption;
    dialog.showModal();
  }));
  document.getElementById("close-image").addEventListener("click",()=>dialog.close());
  dialog.addEventListener("click",event=>{if(event.target===dialog)dialog.close();});
  dialog.addEventListener("close",()=>{image.removeAttribute("src");if(trigger)trigger.focus();});
});
