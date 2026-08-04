<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains over 2000 video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the Laravel [Patreon page](https://patreon.com/taylorotwell).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Cubet Techno Labs](https://cubettech.com)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[Many](https://www.many.co.uk)**
- **[Webdock, Fast VPS Hosting](https://www.webdock.io/en)**
- **[DevSquad](https://devsquad.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[OP.GG](https://op.gg)**
- **[WebReinvent](https://webreinvent.com/?utm_source=laravel&utm_medium=github&utm_campaign=patreon-sponsors)**
- **[Lendio](https://lendio.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).



## Coding Standard
## Do
The development team must follow PSR-12 coding style. 
The development team must use meaningful variable names. 
The development team must use camelCase for variables and functions.
The development team must use PascalCase for class names.
The development team must use PDO prepared statements for database queries.
The development team must handle exceptions using try-catch statements. 
The development team must keep functions short and focused on one task. 
The development team must use semantic HTML elements. 
The development team must indent nested elements consistently.
The development team must include meaningful alt text for images
The development team must use lowercase tag and attribute names. 
The development team must use lowercase kebab-case for class names.
The development team must group related styles together. 
The development team must keep selectors simple and reusable. 
The development team must add comments for major sections. 
The development team must use const whenever possible. 
The development team must use let only when reassignment is required. 
The development team must use descriptive function names. 
The development team must keep functions modular and reusable. 
The development team must validate all user input before processing. 
The development team must use lowercase snake_case for table and column names. 
The development team must use primary keys ending with _id. 
The development team must use prepared statements for all SQL queries. 
The development team must normalize data where appropriate. 
The development team must comment on complex logic or business rules. 
The development team must write PHPDoc comments for public functions. 
The development team must keep comments updated with the code. 
The development team must escape using htmlspecialchars().
The development team must validate uploaded file types and sizes. 
The development team must store controllers, models and views in separate folders. 
The development team must use meaningful file names. 
The development team must keep related files together. 
The development team must write clean, readable and maintainable code. 
The development team must follow consistent formatting throughout the project. 
The development team must test code before committing changes. 
The development team must remove unused code and files. 
The development team must keep functions and classes focused on a single responsibility. 

## Do not
The development team should not write all code on one line or use inconsistent formatting. 
The development team should not use unclear names such as $a, $m or $temp. 
The development team should not mix name styles.
The development team should not use lowercase or inconsistent class names. 
The development team should not concatenate SQL queries with user input. 
The development team should not display raw database or system errors to users.
The development team should not create log functions that perform multiple unrelated tasks. 
The development team should not use excessive <div> elements when semantic tags are available. 
The development team should not mix indentation styles or leave HTML unformatted. 
The development team should not leave image alt attributes empty unless decorative. 
The development team should not mix uppercase and lowercase HTML tags. 
The development team should not use inconsistent names such as .TripName or .tripName.
The development team should not scatter related styles throughout the stylesheet. 
The development team should not use overly complex or deeply nested selectors. 
The development team should not leave stylesheets without any organization. 
The development team should not use var for new variables.
The development team should not declare every variable with let unnecessarily.
The development team should not use ambiguous names like func2() or test().
The development team should not duplicate the same logic in multiple files. 
The development team should not trust all user input without validation. 
The development team should not use inconsistent naming such as UserTable or TravelPreference. 
The development team should not use unclear primary key names like id1 or num. 
The development team should not build SQL statements by concatenating user input. 
The development team should not store duplicate or redundant data unnecessarily. 
The development team should not comment on every obvious statement. 
The development team should not leave important functions undocumented. 
The development team should not leave outdated or misleading comments. 
The development team should not display raw user input directly on web pages. 
The development team should not allow unrestricted file uploads. 
The development team should not place all PHP files in a single folder. 
The development team should not use generic names such as test.php or start.php . 
The development team should not write overly complex code that is difficult to understand. 
The development team should not use different coding styles across files. 
The development team should not commit untested or incomplete code to the repository . 
The development team should not leave commented - out or dead code in the project. 
The development team should not create functions that handle many unrelated tasks. 

