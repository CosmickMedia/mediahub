</div>
<!-- Bootstrap JS from CDN -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
<script>
function checkNotifications(){
    fetch('notifications.php')
        .then(r=>r.json())
        .then(d=>{
            const el=document.getElementById('notifyCount');
            if(el){
                if(d.count>0){
                    el.style.display='inline-block';
                    el.textContent=d.count;
                }else{el.style.display='none';}
            }
        });
}
setInterval(checkNotifications,10000);
checkNotifications();
</script>
</body>
</html>