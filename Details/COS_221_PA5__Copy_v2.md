

COS 221 Practical Assignment 5
## •  Date Issued: 22 April 2026
-  Date Due: 26 May 2026 before 11:00 AM
## •  Demo Date: 27 May 2026
-  Submission Procedure: Upload to ClickUP
-  This assignment consists of 11 tasks for a total of 300 marks and 30 bonus marks
-  There are 10 marks assigned to group cohesion for this practical assignment
## 1  Overview
This last practical assignment in COS221 serves to expose you to projects and activities that will be required of
you in the second semester of your second year as well as on the third-year level, especially COS301.  This project
is a group-based project which will require you to use all of your obtained knowledge in the degree so far plus
the effort of your group to complete this assignment.  This assignment has minimal specifications to allow you to
implement all you have learned, but also discover and showcase to the lecturers and yourself what you are capable
of achieving as a Computer Science student of the University of Pretoria.
For this assignment, you are welcome to do and implement everything that you think is suitable for this project.
This means that you are welcome to do more than what is required, but not less.  Where specifications might seem
vague to yourself or your group,  make the required decisions and assumptions and add them to your uploaded
PDF. Simply put, you are required to use your creativity for this assignment and working in groups will make it
easier to find ideas and implement a good project.  This process is your first introduction to what will be required
of you in your final year and industry as a University of Pretoria graduate.
## 2  Project Scenario
Everybody loves a good vacation, but a great vacation requires extensive planning.  From choosing the destination
and  booking  flights  to  finding  accommodation,  places  to  go  and  what  to  eat,  it  can  become  exhausting.   Even
though travel agencies can ease the workload, there are still issues around price transparency and the trustworthi-
ness of these agencies.  This is where you and your group come in.
Your group has been commissioned to design and develop Tripistry, a travel package platform where travellers
browse and compare packages and agencies create and curate them.  The basic requirements are as follows:
-  Travellers should be able to browse different destinations, flights, accommodations, popular tourist attractions
and go-to restaurants.
-  They should be able to compare prices from different travel agencies, book packages and comment on their
experience with the agency.
-  Travel agencies should be able to showcase the packages they have available as well as create new packages
for travellers.
-  Because some people may want to travel in a group but not have enough companions, agencies should allow
group trips which they manage and organise.
-  The  system  must  enforce  distinct  roles  for  travellers  and  travel  agencies,  reflected  in  both  the  database
schema and the application interface.  These two user types have different permissions and interactions with
the database and cannot share the same interface.

The following resources may be helpful in completing this practical.  You are welcome to use any other resources
applicable to the scenario:
-  UI: https://www.jet2holidays.com/ (Example only, do NOT replicate this UI.)
-  Flight datasets:  https://ansperformance.eu/csv/
-  Hotel and accommodation APIs:  https://rapidapi.com/category/Travel
-  Tourist attraction data:  https://opentripmap.io/
-  Restaurant and destination datasets:  https://www.kaggle.com/datasets
## 3  Outcomes
After successful completion of this assignment you should be able to:
-  Analyse and understand data from multiple sources
-  Curate and clean data for use in a relational database
-  Design a database schema to be implemented in a RDBMS for the curated data
-  Design and build a web-based application and:
–  Execute a connection to an RDBMS from a server-side programming language
–  Query and manipulate a relational database from a server-side programming language
–  Build a web-based interface using HTML, CSS and JavaScript
–  Utilise the web-based interface to query and manipulate a relational database
## 4  Constraints
-  Your solution must be implemented as a web-based application that interacts with a relational database.
-  You may ask the Teaching Assistants for help but they will not be able to give you the solutions.
-  For the design task and the relational model, you are required to use draw.io (https://app.diagrams.net/
or https://draw.io/) or a similar software to create your design.  Unless explicitly stated otherwise, use
Chen’s notation for ER and (E)ER modelling.  Failure to adhere to these requirements may result in marks
being deducted.  This diagram must be included in the submitted PDF.
-  Hand-drawn  diagrams  will not  be  accepted.   Diagrams  must  be  created  using  appropriate  diagramming
software.
-  The PDF, database dump, source code, web-based application and git history will be marked.
-  The web-based application:
(a)  Which runs and performs what it is supposed to will receive full marks
(b)  Which runs but does not perform as required will receive partial marks
(c)  Which does not run will be allocated partial marks based on the functionality it would have exhibited
-  You must use a RDBMS alongside the tools and languages required to build a web-based application.
-  You may utilise any text editor or IDE, upon an OS of your choice.
-  The group will be required to demo the project; failure to demo and adhere to the guidelines set out may
lead to penalties or a mark of zero.

## 5  Milestones
To be able to successfully deliver and demonstrate the project on 27 May 2026, you need to adhere to the following
milestones:
DateMarksMilestone
24–27 April 2026–Register  your  teams.   Teams  must  consist  of  5
members.
13 May 202610Tutor  Progress  Meeting  1:   The  initial  ap-
proach should be completed by this meeting tu-
tors  will  assist  with  any  issues  identified  in  the
demonstrated approach.  ensure your (E)ER dia-
grams and relational mappings are completed.
Show your database with a few entries of valid
data and some queries to your tutor.  Note:  you
are  not  expected  to  have  the  full  database  fi-
nalised at this stage a few sample entries demon-
strating progress are sufficient.
20 May 202610Tutor Progress Meeting 2: Demonstrate your
initial web-based application that integrates with
the  database,  representing  your  minimal  viable
product.
21–22 May 2026–Bookings for the team demonstration will open on
21 May 2026 at 11:00 and close on 22 May 2026
at 11:00.
26 May 202610Individual   submission   due   before   11:00
AM:   Submit   your   self-reflection   essay   (see
## Task 10).
26 May 2026260Group  submission  due  before  11:00  AM:
Submit your group project with all stated require-
ments.
27 May 202640Demonstrate your final project.
Note on Progress Meetings:  The two tutor meetings on 13 May and 20 May are each worth 10 marks and
are assessed individually per team member.  Attendance of all team members and meaningful participation are
required.  No marks will be awarded if a team member fails to pitch up for the meetings.

## 6  Submission Instructions
This assignment has two separate submissions:
Group Submission (due 26 May 2026 before 11:00 AM)
You are required to upload a single archive that includes the following files:
-  An  archive  containing  your  web-based  application.   If  you  used  a  management  tool  you  are  required  to
mention it.
-  A PDF containing the answers to the tasks, as well as the link to your GitHub Repository.
-  A file or files containing the SQL statements from your database dump to:
–  Create your database,
–  Create the tables in your database,
–  Populate the tables with the data you used.
-  Your archive containing your .git folder.
-  A readme.txt or readme.md file (which is also used on GitHub) informing the marker what they should do
to build and execute your application.
Individual Submission (due 26 May 2026 before 11:00 AM)
Each  team  member  must  submit  their  own self-reflection  essay  (Task  9)  as  a  separate  individual  upload  to
ClickUP. This is a one-page essay and must not be included in the group archive.
Upload your archive to ClickUP. No late submissions will be accepted,  so make sure you upload in good
time.
## 7  Online Resources
-  Git: https://git-scm.com
-  Git Helpful Website with commands and more info on Git: https://www.atlassian.com/git
-  GitHub: https://github.com
-  PHP: https://www.php.net
-  W3Schools :https://www.w3schools.com/
-  Composer: https://getcomposer.org
-  Platform for developers to learn, share knowledge and build a career: https://stackoverflow.com/

COS 221 Practical Assignment 5: 22 April 2026
## 8  Assignment Instructions
Task 1:  Research  . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . (20 marks)
Conduct research on the vacation and travel industry, focusing on online holiday planning and price compar-
isons.  Include a minimum of  four references (using IEEE or ACM format) and limit your research to one
page;  the references can continue onto another page.  Cover aspects such as existing travel agencies,  travel
package comparison tools, popular destinations, accommodation providers and tourist attraction platforms.
Task 2:  (E)ER Diagram  . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . (30 marks)
After you have conducted your research in Task 1, you are now required to construct an (E)ER-diagram of
your project and provide the final model in your uploaded PDF. You are required to mention all assumptions
you have made during your modelling and any other information you deem necessary.  If you made multiple
iterations to reach your final (E)ER-diagram, be sure to include all iterations, as well as notes on how each
iteration improves on the previous.
Task 3:  (E)ER Diagram to Relational Mapping  . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . (30 marks)
Apply the steps for converting your (E)ER-diagram into a relational model.  Provide a relational mapping of
your (E)ER-diagram in your uploaded PDF. Be sure to indicate the conversion for each step, as well as the
assumptions you have made and the choice you have made where a conversion could provide multiple solutions.
Task 4:  Relational Schema  . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . (30 marks)
Design a relational database schema based on your mappings obtained in Task 3 in the form of both a visual
diagram and SQL statements targeted towards MariaDB. Ensure that you include, where applicable, all of the
following:
-  Primary, Secondary and Foreign Keys
-  Constraints and Checks
-  Data Types and Length Constraints
Task 5:  Web-Based Application  . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . (55 marks)
Create a web application using PHP, HTML5, CSS and JavaScript to manage and perform the queries you
find necessary for Tripistry.  Your web application must at a minimum be able to:
-  Log in and manage two distinct user types: Travellers and Travel Agencies.
-  Allow travellers to browse destinations, flights, accommodations, tourist attractions and restaurants.
-  Allow travellers to compare travel packages across different agencies and book a selected package.
-  Allow travellers to leave reviews and ratings for agencies and their packages.
-  Allow travel agencies to create, edit and delete travel packages and group trip offerings.
-  Allow travel agencies to manage the destinations, flights, accommodations, restaurants and attractions
associated with a package.
-  Sort and filter packages based on various criteria (price, destination, duration, rating, etc.).
-  Display a detailed package view including pricing, itinerary, images, agency information and reviews.
-  SQL injection prevention.
Task 6:  Data  . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . (20 marks)
To ensure your web application has sufficient data for testing and demonstration purposes, you are required to
populate your database with a substantial amount of travel packages, destinations, flights, accommodations,
tourist attractions, restaurants, agencies and user reviews.
You are welcome to augment the given data and/or include all your relevant data in your database by hand,
by using a script that generates artificial data, a script that parses data from an API, or by loading data using
XML/JSON feeds from relevant websites or APIs (that you find or write yourself).
You must explain your data population method and the reasoning behind your choices in your uploaded
## PDF.
Page 1 of 4

COS 221 Practical Assignment 5: 22 April 2026
Task 7:  Analyse and Optimise  . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . (10 marks)
You are required to make use of your chosen RDBMS and tools to analyse at least one of your query execution
plans and report on the performance in your uploaded PDF. Thereafter you are required to explain how you
would optimise your query,  implement your proposed optimisation and report on the performance gains or
losses achieved.  You are also required to explain why you believe the observed gain or loss in performance was
observed.
Hint:   Ensure  that  you  have  sufficient  data  in  your  database,  or  else  you  might  not  observe  a  meaningful
difference.
Task 8:  Development  . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . (25 marks)
Your project will be assessed not only on the functionality it provides, but also on your overall development
practices such as:
-  Usage of git (commits, branching strategy, meaningful messages)
-  Data validation techniques (client-side and server-side)
-  Utilisation of a package manager
-  Ease of understanding, spelling and grammar and structure in your code, git commit messages, uploaded
PDF and README
-  Quality of the overall delivered solution
Task 9:  Demo  . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . (40 marks)
Your team will be required to demo your solution to the lecturers of COS 221.  Booking slots will be opened
closer to the time.  Note that all team members are required to be present for the demo in order to
receive marks.  In addition, each team member must explain their individual contributions to the project in
the uploaded PDF. The demo will be evaluated based on functionality, user experience, presentation quality,
and individual contributions.
Task 10:  Self-Reflection  . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . (10 marks)
This is an individual submission and must be submitted separately from the group archive before 11:00
AM on 26 May 2026 via ClickUP.
Write a one-page reflective essay (approximately 400–500 words) addressing the following:
-  Your personal contributions to the group project and how they fit into the overall solution.
-  Challenges you encountered during the project and how you overcame them.
-  What you learned from working in a group on a large-scale project.
-  What you would do differently if you were to start the project again.
Your reflection will be assessed on depth of insight, honesty and quality of writing.  This task is worth
10 marks and is assessed individually, each team member submits their own essay.
Task 11:  Bonus Task – Spice It Up  . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . (30 marks)
This task is designed to encourage creativity, innovation and independent learning.  You may choose one or
more of the options below, each of which introduces a new concept or advanced application beyond what was
required  in  the  main  project.The  task  and  how  it  was  implemented  must  be  documented  and  explained  in
your submission.  You may also do something not listed below, it will be up to the discretion of the markers
to determine if your chosen innovation counts as something impressive.
-  Enhanced UI/UX
Create an interactive traveller-side dashboard showing the following:
-  Top-rated packages
-  Agency statistics
-  Review data from frequent travellers
You may also consider features such as skeleton loading screens and micro-animations for a polished feel.
Page 2 of 4

COS 221 Practical Assignment 5: 22 April 2026
## 2.  Security Features
Conduct a basic security audit and implement two or more improvements, such as:
-  Secure password hashing (elaborate on your choice of hashing algorithm and techniques)
-  Login attempt rate-limiting
-  CSRF/XSS protection
-  Monitoring, through audit or error logs
-  Session management improvements
## 3.  Algorithmic Thinking
Because not every traveller has a group, design an algorithm that matches solo or small-party travellers
into compatible groups for agency group trips.  Your matching criteria should be informed by data al-
ready present in your schema (e.g. destination preference, budget, travel dates) and the algorithm’s logic,
complexity and trade-offs should be discussed in your PDF.
-  AI Integration
Meaningfully integrate AI into your application.  Simply connecting to a model’s API will earn you no
marks in this task.  Consider the following:
-  Implementing sentiment analysis for reviews
-  Trip summaries or descriptions from structured package data
## 5.  Advanced Feature
Design and implement one unique feature not already required.  Examples:
-  A recommendation engine that suggests travel packages based on user history or preferences
-  An interactive map view of destinations and attractions
-  An option to integrate trip plans with a traveler’s calendar
## 6.  Advanced Git Features
Version control and automation are essential practices in modern software development.  Implement Con-
tinuous Integration (CI) using GitHub Actions to streamline your development workflow and ensure code
quality through automated testing.
Note:  This task will require a proper git branching strategy to work well.
## IMPORTANT NOTES:
-  Please refer to the rubric on the last page for the detailed allocation of marks.
-  You will be required to demo your project.
-  If you do not demo, you will receive 0.
-  Attendance  at both  tutor  progress  meetings  (13  May  and  20  May)  is  required  to  receive  the  associated
marks.
-  The self-reflection essay (Task 10) is an individual submission and must be submitted separately before
11:00 AM on 26 May 2026.
Page 3 of 4

COS 221 Practical Assignment 5: 22 April 2026
## Research20
General overview and explanation4
Types or categories of travel products and agencies explained4
Information on how content is rated or categorised4
Additional features/information (Recommendations, Group Trips, User Reviews)4
## References4
(E)ER Diagram30
Entities and Attributes10
Complex and Derived Attributes10
Relationships and Cardinality10
## Mapping30
## Regular Entity Types3
## Weak Entity Types3
## 1:1 Relationships3
1:N Relationships3
M:N Relationships3
## Multivalued Attributes3
## N-ary Relationships3
Specialisation and Generalisation3
## Unions3
## Correctness3
## Relational Schema30
## Visual Representation10
Primary, Secondary and Foreign Keys5
Constraints and Checks5
Data Types and Length Constraints5
## Correctness5
Web-Based Application55
Functional Requirements (traveller & agency features)15
Package and Booking Management Features10
User Account and Profile Management (two distinct interfaces)10
Sorting and Filtering Options10
## Queries10
SQL Injection Prevention5
## Sample Data20
Explanation of Data Sources5
Data Entry Methods (Script or Manual)5
Quality and Relevance of Data10
Analysis and Optimisation10
## Explanation2
## Optimisation3
## Interpretation5
## Development25
Version Control (Git)5
README Documentation5
Overall Quality and Impression15
## Demo40
Functionality and User Experience20
## Presentation Quality20
## Group Cohesion10
## Progress Meetings20
Tutor Meeting 1 13 May 2026 (database progress & initial approach)10
Tutor Meeting 2 20 May 2026 (minimal viable product demo)10
Self-Reflection10
Depth of insight and personal reflection4
Identification of challenges and lessons learned3
Quality of writing and structure3
## Total300
## Bonus Task30
Page 4 of 4