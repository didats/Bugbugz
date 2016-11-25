## BugBugz
Open source Bug tracker app made with [Silex](http://silex.sensiolabs.org) on top of [Crud Admin Generator](https://github.com/jonseg/crud-admin-generator). 
This small app is for you who need the simplicity. We at [Rimbunesia](https://rimbunesia.com) currently using this small app and still in heavy development

## The flow
1. `TESTER` create a Project
2. `TESTER` create an issue under a project and assigned to `DEVELOPER`. It may include the attachments (currently only support an image attachment)
3. `DEVELOPER` listed all the issue under a project. And `SET AS DONE` if its fixed.
4. The issue that already `SET AS DONE` by `DEVELOPER` will be back to the `TESTER` as `REVIEW` status
5. The `TESTER` may decide the issue is `DONE` or need to `OPEN` again.
6. The `DEVELOPER` could set the issue as `FAILED`
7. The issue with `FAILED` status will go back to the `TESTER` and he/she could `SET AS DONE` or `OPEN`

## Features
1. There are 3 types of user. `ADMIN`, `DEVELOPER`, and `TESTER`
2. Each issue has a comment feature to enable DEVELOPER and TESTER interact each other
3. Issue has 4 status, OPEN, REVIEW, FAILED, DONE
4. Each issue could upload up to 3 image attachments

## Coming soon (plan) FEATURES
1. We will provide iOS and Android App and works with your own server.
2. Push Notification on both iOS and Android
3. Email Notification
4. Slack Channel Notification
5. Themes
6. Installation wizard

### How to install
Get the source code from Github

    git clone https://github.com/didats/Bugbugz.git bugbugz

    cd bugbugz

Run the composer. If you don't have composer, google it.

    composer install

Go to `src/app.php` and edit the Mysql connection there.

Import the database to your own server. We provide you with one `ADMIN` account with username and password `admin`:`tester123`.

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

![ScreenShot](/screenshots/login.png)
![ScreenShot](/screenshots/create_issue.png)
![ScreenShot](/screenshots/detail_issue.png)

### Author

* Didats Triadi <didats@gmail.com>
* Personal site: [http://didats.net](http://didats.net)
* Company site: [https://rimbunesia.com](https://rimbunesia.com)
* Twitter: [@didats](https://twitter.com/didats)

### About Rimbunesia

We are macOS, iOS & Android application development studio based in Malang, Indonesia. [You may visit our website](https://rimbunesia.com) to know more what kind of apps we have built.
