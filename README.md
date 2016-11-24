## BugBugz
Open source Bug tracker app based on Silex on top of [Crud Admin Generator](https://github.com/jonseg/crud-admin-generator). 
This small app is for you who need the simplicity. We at [Rimbunesia](https://rimbunesia.com) are using this and it is currently 
on developing mode. Use with your own risk.

### How to install
Get the source code from Github

    git clone https://github.com/didats/Bugbugz.git bugbugz

    cd bugbugz

Run the composer. If you don't have composer, google it.

    composer install

Go to `src/app.php` and edit the Mysql connection there.

As we are using NGINX, this is the configuration. I am not sure how about Apache. You may go to the [official site of Silex](http://silex.sensiolabs.org) and read the documentation there.

	server {
    listen         80;
    server_name    bugbugz.local;
    root        /Users/didats/Web/bugbugz/web;
    index index.php;
    location ~* ^.+\.(jpg|jpeg|gif|png|ico|css|txt|js|map)$ {
        expires 1d;
    }

    location / {
                if (-f $request_filename) {
                        expires max;
                        break;
                }

                rewrite ^(.*) /index.php last;
        }

    location ~ \.php$ {
            include        fastcgi_params;
            fastcgi_pass   127.0.0.1:9000;
            fastcgi_param  SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
	}

### Some screenshots

Open the screenshot directory to see the other result.

!/screenshots/login.png
!/screenshots/create_issue.png
!/screenshots/detail_issue.png

### Author

* Didats Triadi <didats@gmail.com>
* Personal site: [http://didats.net](http://didats.net)
* Company site: [https://rimbunesia.com](https://rimbunesia.com)
* Twitter: [@didats](https://twitter.com/didats)
