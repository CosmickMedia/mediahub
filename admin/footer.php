</div>
<!-- Bootstrap JS from CDN -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
<?php if (is_logged_in()): ?>
<script>
function checkNotifications(){
    fetch('store_counts.php')
        .then(r=>r.json())
        .then(list=>{
            const total=list.reduce((s,v)=>s+parseInt(v.unread||0),0);
            const wrap=document.getElementById('notifyWrap');
            const countEl=document.getElementById('notifyCount');
            if(wrap){
                if(total>0){
                    wrap.style.display='inline-block';
                    countEl.style.display='inline-block';
                    countEl.textContent=total;
                }else{
                    wrap.style.display='none';
                }
            }
            if(typeof updateStoreCounts==='function'){updateStoreCounts(list,total);}
        });
}
setInterval(checkNotifications,5000);
checkNotifications();
</script>
<?php endif; ?>
<?php $version = trim(file_get_contents(__DIR__.'/../VERSION')); ?>
<div class="position-fixed bottom-0 end-0 p-2 text-muted small">
    v<?php echo $version; ?>
</div>
</body>
</html>
