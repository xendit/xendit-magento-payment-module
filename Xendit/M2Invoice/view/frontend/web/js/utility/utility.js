define([], () => {
    {

        /**
         *  This function used for set the cookies
         * @param key {string} cookie name
         * @param value {string} cookie value
         */

        function setCookie(key, value, session) {
            let now = new Date();
            let time = now.getTime();
            time += 3600 * 500;
            now.setTime(time);
            let expires = "";
            if (session && session == true) {
                expires = "; expires="+0;
            } else {
                expires = "; expires="+now.toUTCString();
            }
            document.cookie = escape(key)+"="+escape(value)+expires+"; path=/";
        }

        /**
         *  This function used for get the cookie value
         * @param key {string} cookie name
         * @returns {string}
         */
        function getCookie(key){
            let nameEQ = escape(key) + "=";
            let ca = document.cookie.split(';');
            for(let i=0;i < ca.length;i++) {
                let c = ca[i];
                while (c.charAt(0)==' ') c = c.substring(1,c.length);
                if (c.indexOf(nameEQ) == 0) return unescape(c.substring(nameEQ.length,c.length));
            }
            return null;
        }


        return {
            setCookie,
            getCookie
        }
    }
});
