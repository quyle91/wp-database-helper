@echo off
rem Script to copy and synchronize source folder with Git folder

rem Define variables for source folder and Git folder
set SOURCE_FOLDER=D:\Laragon\www\flatsome\wp-content\plugins\administrator-z\vendor\quyle91\wp-database-helper
set GIT_FOLDER=D:\source\git\wp-database-helper\

rem Display folder paths
echo ::: Source Folder: %SOURCE_FOLDER%
echo ::: Git Folder: %GIT_FOLDER%
echo.

rem Keep .git folder, only delete other contents
echo ::: Cleaning Git folder...
for /D %%d in ("%GIT_FOLDER%\*") do if /I not "%%~nd"==".git" rmdir /S /Q "%%d"
for %%f in ("%GIT_FOLDER%\*") do if /I not "%%~nxf"==".git" del /Q "%%f"

rem Copy all files from the source folder to the Git folder
echo ::: Copying files from source folder...
xcopy "%SOURCE_FOLDER%\*" "%GIT_FOLDER%\" /E /I

rem Move into the Git folder
cd /D "%GIT_FOLDER%"

rem Ensure Git repository exists
echo ::: Checking Git repository...
if not exist .git (git init)

rem Add all files to Git index
echo ::: Adding files to Git...
git add .

rem Commit changes
echo ::: Committing changes...
git commit -m "wp-database-helper new release date %date% %time%"

rem Push changes to remote repository
echo ::: Pushing changes to remote repository...
git push origin main

echo ::: Done!
