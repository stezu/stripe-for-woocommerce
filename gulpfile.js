'use strict';

var gulp       = require('gulp'),
    sourcemaps = require('gulp-sourcemaps'),
    jshint     = require('gulp-jshint'),
    stylish    = require('jshint-stylish'),
    uglify     = require('gulp-uglify'),
    rename     = require('gulp-rename');

var files = {
    js   : ['./assets/js/s4wc.js'],
};

gulp.task('lint', function () {

    gulp.src(files.js)
        .pipe(jshint())
        .pipe(jshint.reporter(stylish));
});

gulp.task('js', function () {

    gulp.src(files.js)
        .pipe(rename({
            suffix: '.min'
        }))
        .pipe(sourcemaps.init())
        .pipe(uglify())
        .pipe(sourcemaps.write('./'))
        .pipe(gulp.dest('./assets/js/'));
});

gulp.task('watch', ['default'], function () {
    gulp.watch(files.js, ['lint', 'js']);
});

gulp.task('default', ['lint', 'js']);
