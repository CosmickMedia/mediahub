</div>
<!-- Bootstrap JS from CDN -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
<!-- CountUp JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/countup.js/2.8.0/countUp.umd.min.js"></script>
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
                            wrap.style.display='inline-flex';
                            countEl.style.display='block';
                            countEl.textContent=total;
                        }else{
                            countEl.style.display='none';
                        }
                    }
                    if(typeof updateStoreCounts==='function'){updateStoreCounts(list,total);}
                });
        }
        setInterval(checkNotifications,5000);
        checkNotifications();

        // Animate counters on page load
        document.addEventListener('DOMContentLoaded', function() {
            const counters = document.querySelectorAll('[data-count]');
            counters.forEach(counter => {
                const target = parseInt(counter.getAttribute('data-count'));
                const animation = new countUp.CountUp(counter, target, {
                    duration: 2,
                    useEasing: true,
                    useGrouping: true
                });
                if (!animation.error) {
                    animation.start();
                }
            });
        });
    </script>
<?php endif; ?>
<?php $version = trim(file_get_contents(__DIR__.'/../VERSION')); ?>
<div class="version-badge">
    v<?php echo $version; ?>
</div>
</body>
</html>