{extends designs/site.tpl}

{block title}Pull from ScholarChip &mdash; {$dwoo.parent}{/block}

{block content}
    <h1>Pull from ScholarChip</h1>
    <h2>Instructions</h2>
    <ul>
        <li>Export student photos and upload <code>.zip</code> file here. Use pretend mode first to check changes.</li>
    </ul>

    <h2>Input</h2>

    <h3>Run a new job</h3>
    <form method="POST" enctype="multipart/form-data">
        <fieldset>
            <legend>Job Configuration</legend>
            <p>
                <label>
                    Pretend
                    <input type="checkbox" name="pretend" value="true" {refill field=pretend checked="true" default="true"}>
                </label>
                (Check to prevent saving any changes to the database)
            </p>
            <p>
                <label>
                    Email report
                    <input type="text" name="reportTo" {refill field=reportTo} length="100">
                </label>
                Email recipient or list of recipients to send post-sync report to
            </p>
        </fieldset>

        <fieldset>
            <legend>Student photos</legend>
            <p>
                <label>
                    Student photos <code>.zip</code>
                    <input type="file" name="studentPhotos" accept=".zip">
                </label>
            </p>
        </fieldset>

        <input type="submit" value="Synchronize">
    </form>
{/block}