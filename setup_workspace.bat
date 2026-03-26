@echo off
setlocal EnableDelayedExpansion

echo ===================================================
echo Initializing DX Engine Framework Workspace...
echo ===================================================
echo.

REM Define all directories separated by spaces. 
REM Windows batch files prefer backslashes (\) for directory paths.
set "directories=bin config database\migrations database\seeds public\api public\js public\css src\Core\Contracts src\Core\Traits src\Core\Middleware src\Core\Migrations src\Core\Jobs src\Core\Exceptions src\App\Models src\App\DX storage\logs storage\cache storage\exports templates\layouts templates\portals templates\partials tests\Unit\Core\Jobs tests\Integration\Api tests\Feature"

REM Loop through the list, create the directory, and generate the .gitkeep file
for %%d in (%directories%) do (
    echo Creating: %%d
    
    REM Create the directory tree (suppress errors if it already exists)
    mkdir "%%d" 2>nul
    
    REM Create an empty .gitkeep file inside the leaf directory
    type NUL > "%%d\.gitkeep"
)

echo.
echo ===================================================
echo Directory structure and .gitkeep files created!
echo ===================================================
pause