# LaraStorage

## About
This is a learning project made with the video guidance from excellent YouTube tutorial channel [freeCodeCamp.org](https://www.youtube.com/@freecodecamp). I am very grateful for many excellent videos with Laravel projects to Mr. Zura from [@TheCodeholic](https://www.youtube.com/channel/UC_UMEcP_kF0z4E6KbxCpV1w). Feel free to visit to his channel and learn more.

Built with these technologies:
<table>
    <tr>
        <td>
            <a href="https://laravel.com" title="Laravel"><img src="https://i.imgur.com/pBNT1yy.png" /></a>
        </td>
        <td>
            <a href="https://vuejs.org/" title="VueJS"><img src="https://i.imgur.com/BxQe48y.png" /></a>
        </td>
        <td>
            <a href="https://tailwindcss.com/" title="TailwindCSS"><img src="https://i.imgur.com/wdYXsgR.png" /></a>
        </td>
    </tr>
</table> 


## Prerequisites
Build on Laravel 10 starter template with Vue, Tailwindcss and Inertia.js. If you run this project outside Docker container, you need to have Node installed. 


## Installation

1. Download the project (or use `git clone`)
2. Go to the project's root directory using terminal
3. Run `composer install`
4. Create database for project
5. Copy `.env.example` into `.env` file

    `cp .env.example .env`

   and configure database credentials. Adjust other  parameters, if needed.
6. Run DB migrations:

   `php artisan migrate`

7. Generate app key:

    `php artisan key:generate --ansi`

8. Create a link to public storage folder:

   `php artisan storage:link`
 
9. Run `npm run build` to prepare assets for production (or e.g. if you want PHPStorm to show TW classes in typehints)
10. Run `npm run dev` to start the local development server at http://localhost

## Notes

If you intend to set up external filesystem disk, e.g. AWS s3, ensure you have correct credentials stated in your .env file and `FILESYSTEM_DISK` parameter. If it is not set to `local`, for every file upload, `UploadFileToCloudJob` class wil be triggered if Laravel queue started.
If you want to run Laravel queue worker on hosting server, consider running it with `nohup`:

`nohup php artisan queue:work --daemon &`

To check (or kill) for the running artisan processes run:
`ps -ax | grep artisan`
