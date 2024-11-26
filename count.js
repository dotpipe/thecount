
        function fetchSessionId() {                             
                fetch('update_sess.php')
                    .then(response => response.text())
                    .then(sessionId => {
                        console.log('Session ID:', sessionId);
                    })
                    .catch(error => {
                        console.error('Error fetching session ID:', error);
                    });
        }
        setInterval(fetchSessionId, 5000); // 5000ms = 5 seconds
