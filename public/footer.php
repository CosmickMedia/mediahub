</div>
<!-- Bootstrap JS from CDN -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
<script>
function checkNotifications(){
    fetch('notifications.php')
        .then(r=>r.json())
        .then(d=>{
            const wrap=document.getElementById('notifyWrap');
            const count=document.getElementById('notifyCount');
            if(!wrap) return;
            if(d.count>0){
                wrap.style.display='inline-block';
                count.style.display='inline-block';
                count.textContent=d.count;
            }else{
                wrap.style.display='none';
            }
        });
}
setInterval(checkNotifications,5000);
checkNotifications();
</script>
<?php if(isset($extra_js)) echo $extra_js; ?>
</body>
</html>